<?php

namespace App\Services\Generator;

use App\Models\GeneratorOption;
use App\Models\UiGeneration;
use RuntimeException;

/**
 * Turns one brief into a small multi-screen project with the local Qwen model.
 *
 *   1. research  – keyless web search, then reads the top pages for ideas
 *   2. plan      – Qwen fixes a brand, palette and what each screen holds
 *   3. design    – Qwen writes an HTML preview for every screen
 *   4. code      – Qwen rewrites each preview in the chosen framework
 *   5. scaffold  – router / entry / config files are added around the screens
 *
 * Progress is written to the generation after every step so the browser
 * can show it while the queue worker runs.
 */
class ProjectGenerator
{
    public function __construct(
        private QwenClient $qwen,
        private InspirationScout $scout,
        private ProjectScaffold $scaffold,
    ) {}

    public function run(UiGeneration $generation): void
    {
        $screens = $generation->screens ?? [];
        $domains = $generation->domains ?? [];
        $framework = $this->framework($generation);
        $steps = 3 + count($screens) * ($framework['preview_is_code'] ? 1 : 2);
        $step = 0;

        $generation->forceFill(['status' => 'running', 'error' => null])->save();

        // 1. Research -------------------------------------------------------
        $domainNames = array_column($domains, 'name');
        $screenNames = array_column($screens, 'name');
        if ($generation->use_research) {
            $generation->report('Searching the web for '.(implode(', ', $domainNames) ?: 'similar').' interfaces', $step, $steps);
            $research = $this->scout->researchProject($generation->prompt, $generation->platform, $screenNames, $domainNames);
        } else {
            $research = ['query' => '', 'source' => 'skipped', 'references' => [], 'ideas' => ''];
        }
        $this->ensureAlive($generation);

        $profile = DomainProfiles::forPrompt($generation->prompt.' '.implode(' ', $domainNames), $domains[0]['slug'] ?? null);
        $direction = DesignDirection::pick($generation->prompt, $generation->style, $generation->accent, $profile['key'], $research['references']);

        // 2. Plan -----------------------------------------------------------
        $generation->report('Qwen is planning the brand and '.count($screens).' screen(s)', ++$step, $steps);
        $plan = $this->plan($generation, $direction, $research);
        $this->ensureAlive($generation);

        // 3 + 4. Each screen: preview, then code ------------------------------
        $previews = [];
        $files = [];
        foreach ($screens as $i => $screen) {
            $label = $screen['name'].' ('.($i + 1).'/'.count($screens).')';

            $generation->report("Designing {$label}", ++$step, $steps);
            $html = $this->preview($generation, $screen, $plan, $direction, $research, $framework);
            $previews[] = ['slug' => $screen['slug'], 'name' => $screen['name'], 'html' => $html];
            $generation->forceFill(['previews' => $previews, 'html' => $previews[0]['html']])->save();
            $this->ensureAlive($generation);

            if ($framework['preview_is_code']) {
                foreach ($framework['files'] ?: ['{slug}.html'] as $pattern) {
                    $files[] = ['path' => FrameworkCatalog::path($pattern, $screen['slug']), 'language' => 'html', 'screen' => $screen['slug'], 'content' => $html];
                }
            } else {
                $generation->report("Writing {$framework['name']} code for {$label}", ++$step, $steps);
                array_push($files, ...$this->code($generation, $screen, $html, $plan, $framework));
            }
            $generation->forceFill(['files' => $files])->save();
            $this->ensureAlive($generation);
        }

        // 5. Scaffold + previews in the download ---------------------------
        $generation->report('Packing the project files', ++$step, $steps);
        $extra = $this->scaffold->files($framework, $screens, $plan, $generation);
        foreach ($previews as $p) {
            $extra[] = ['path' => 'design-preview/'.$p['slug'].'.html', 'language' => 'html', 'screen' => $p['slug'], 'content' => $p['html']];
        }
        $all = $this->uniquePaths([...$files, ...$extra]);

        $generation->forceFill([
            'files' => $all,
            'previews' => $previews,
            'html' => $previews[0]['html'] ?? null,
            'screen_type' => $screens[0]['slug'] ?? null,
            'provider' => 'local',
            'status' => 'done',
            'meta' => [
                'brand' => $plan['brand'],
                'tagline' => $plan['tagline'],
                'palette' => array_values(array_filter([$plan['primary'], $plan['secondary']])),
                'font' => $plan['font'],
                'model' => $this->qwen->model(),
                'framework_name' => $framework['name'],
                'language_name' => $framework['language'],
                'direction' => $direction['label'],
                'language' => $direction['language'],
                'summary' => DesignDirection::summary($direction),
                'research' => [
                    'source' => $research['source'],
                    'query' => $research['query'],
                    'references' => array_map(fn ($r) => array_intersect_key($r, array_flip(['title', 'url', 'source', 'ideas'])), $research['references']),
                ],
            ],
        ])->save();
        $generation->report('Done: '.count($previews).' screen(s) and '.count($all).' file(s)', $steps, $steps);
    }

    /** @return array<string, mixed> */
    public function framework(UiGeneration $generation): array
    {
        $option = GeneratorOption::where('type', 'framework')->where('slug', $generation->framework)
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $generation->user_id))
            ->orderByRaw('case when user_id is null then 0 else 1 end')->first();

        $languageName = GeneratorOption::where('type', 'language')->where('slug', $generation->language)
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $generation->user_id))
            ->value('name') ?? (string) $generation->language;
        $name = $option?->name ?? (string) $generation->framework;
        $meta = $option?->meta ?: FrameworkCatalog::generic($name, $languageName);

        return [
            'slug' => $option && $option->user_id === null ? (string) $generation->framework : 'custom',
            'name' => $name,
            'language' => $languageName,
            'fence' => $meta['fence'] ?? '',
            'files' => $meta['files'] ?? [],
            'notes' => $meta['notes'] ?? '',
            'cdn' => $meta['cdn'] ?? null,
            'preview_is_code' => (bool) ($meta['preview_is_code'] ?? false),
        ];
    }

    // ------------------------------------------------------------------ steps

    /** @return array{brand: string, tagline: string, primary: ?string, secondary: ?string, font: ?string, screens: array<string, string>} */
    private function plan(UiGeneration $g, array $direction, array $research): array
    {
        $screenList = collect($g->screens)->map(fn ($s) => "\"{$s['slug']}\": \"what the {$s['name']} screen shows\"")->implode(",\n    ");
        $user = implode("\n", array_filter([
            'Product brief: '.$g->prompt,
            'Domain: '.(collect($g->domains)->pluck('name')->implode(', ') ?: 'infer it from the brief'),
            'Platform: '.$g->platform.'. Visual style: '.$g->style.'.'.($g->accent ? " Primary colour must be {$g->accent}." : ''),
            'Screens to plan: '.collect($g->screens)->pluck('name')->implode(', '),
            $research['ideas'] ? "Ideas collected from real websites (patterns only, do not copy):\n".$research['ideas'] : null,
            '',
            'Return ONLY this JSON object, no other text:',
            '{',
            '  "brand": "an original product name",',
            '  "tagline": "short tagline",',
            '  "primary": "#hex", "secondary": "#hex",',
            '  "font": "a Google Font family name",',
            '  "screens": {',
            "    {$screenList}",
            '  }',
            '}',
            'For each screen write one sentence listing its concrete sections and the real content in them, following the brief.',
        ], fn ($line) => $line !== null));

        $data = [];
        try {
            $text = $this->qwen->chat('You are a senior product designer. You answer with valid JSON only.', $user, (int) config('services.local_ai.plan_tokens', 600), 0.7);
            $data = $this->json($text);
        } catch (RuntimeException $e) {
            // Unreachable model is fatal; a malformed plan just falls back to defaults.
            if (str_contains($e->getMessage(), 'could not be reached')) {
                throw $e;
            }
        }

        $hex = fn ($v) => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : null;
        $screens = [];
        foreach ($g->screens as $s) {
            $screens[$s['slug']] = is_string($data['screens'][$s['slug']] ?? null) && trim($data['screens'][$s['slug']]) !== ''
                ? $data['screens'][$s['slug']]
                : "A complete {$s['name']} screen for this product, following the brief.";
        }

        return [
            'brand' => mb_substr(trim((string) ($data['brand'] ?? '')), 0, 40) ?: 'Brand',
            'tagline' => mb_substr(trim((string) ($data['tagline'] ?? '')), 0, 90),
            'primary' => $g->accent ?: ($hex($data['primary'] ?? null) ?? ($direction['accent'] ?? null)),
            'secondary' => $hex($data['secondary'] ?? null),
            'font' => is_string($data['font'] ?? null) && $data['font'] !== '' ? mb_substr($data['font'], 0, 40) : null,
            'screens' => $screens,
        ];
    }

    private function preview(UiGeneration $g, array $screen, array $plan, array $direction, array $research, array $framework): string
    {
        $viewport = match ($g->platform) {
            'mobile' => 'a mobile app screen for a 390px-wide phone (status bar at the top; a bottom tab bar when it suits the screen)',
            'tablet' => 'a tablet screen for an 820px-wide viewport',
            default => 'a desktop web page for a 1280px-wide viewport',
        };
        $others = collect($g->screens)->map(fn ($s) => "{$s['name']} (href=\"#{$s['slug']}\")")->implode(', ');
        $cdn = $framework['preview_is_code'] && $framework['cdn']
            ? "Build it with {$framework['name']}: put this in <head>: {$framework['cdn']}"
            : 'Put all CSS in one <style> tag. Google Fonts is the only external resource allowed.';
        $domains = collect($g->domains)->pluck('name')->implode(' / ');

        $system = <<<SYS
You are a senior UI designer who writes production-quality HTML/CSS screens.
Return ONLY one complete HTML document starting with <!DOCTYPE html>. No markdown, no explanation.
Rules:
- {$cdn}
- No images at all: never use <img>, data: URIs or base64. Draw avatars as a coloured circle with initials, icons as emoji or a short inline <svg>, pictures as CSS gradients.
- Follow the brief and the screen description literally, with specific realistic copy and data (names, prices, dates, numbers). No lorem ipsum.
- Every screen of one product shares the same brand, colours, fonts and navigation.
- Clear hierarchy, consistent spacing, accessible contrast, responsive layout.
- Forms have labels, buttons say what they do, lists show several realistic items.
- Never copy a real company's brand or logo. Finish the closing </html> tag.
SYS;

        $user = implode("\n", array_filter([
            "Design the \"{$screen['name']}\" screen as {$viewport}.",
            'Product brief: '.$g->prompt,
            'Domain: '.($domains ?: 'infer from the brief'),
            "Brand: {$plan['brand']}".($plan['tagline'] ? " - {$plan['tagline']}" : ''),
            'Colours: primary '.($plan['primary'] ?? 'suited to the domain').($plan['secondary'] ? ", secondary {$plan['secondary']}" : '').'.',
            'Typography: '.($plan['font'] ? "{$plan['font']} from Google Fonts" : $direction['display']).". Corner radius {$direction['radius']}. Visual style: {$g->style} ({$direction['label']}).",
            "This screen must contain: {$plan['screens'][$screen['slug']]}",
            "Navigation links to the other screens of the product: {$others}.",
            $research['ideas'] ? "Interface ideas found on the internet for this kind of product (use the patterns, write your own content):\n{$research['ideas']}" : null,
            'Combine those with what you know works well in '.($domains ?: 'this kind of').' products.',
        ]));

        $text = $this->qwen->chat($system, $user, (int) config('services.local_ai.preview_tokens', 2600), 0.65);
        $html = $this->extractHtml($text);

        if ($html === null) {
            throw new RuntimeException("Qwen did not return an HTML page for the {$screen['name']} screen.");
        }

        return $html;
    }

    /** @return array<int, array{path: string, language: string, screen: string, content: string}> */
    private function code(UiGeneration $g, array $screen, string $html, array $plan, array $framework): array
    {
        $expected = array_map(fn ($p) => FrameworkCatalog::path($p, $screen['slug']), $framework['files']);
        $notes = strtr($framework['notes'], [
            '{Name}' => FrameworkCatalog::pascal($screen['slug']),
            '{slug}' => FrameworkCatalog::path('{slug}', $screen['slug']),
            '{snake}' => FrameworkCatalog::path('{snake}', $screen['slug']),
        ]);
        $fileLines = $expected
            ? "Write exactly these files:\n".implode("\n", array_map(fn ($p) => "- {$p}", $expected))
            : 'Choose the conventional file path(s) for this screen.';
        $fence = $framework['fence'] ?: 'code';

        $system = <<<SYS
You are a senior {$framework['name']} developer writing {$framework['language']}.
You convert an HTML/CSS design into clean, working {$framework['name']} code that looks the same.
Output ONLY files, each in this exact format, nothing before or after:
### FILE: path/to/file
```{$fence}
file content
```
Keep the same layout, colours, fonts, text and data as the design. Move repeated items into arrays/lists. Write every file completely, with no "rest of code" placeholders.
SYS;

        $user = implode("\n\n", [
            "Screen: {$screen['name']} of {$plan['brand']}. Other screens: ".collect($g->screens)->pluck('name')->implode(', ').'.',
            "{$framework['name']} rules: {$notes}",
            $fileLines,
            "Design to convert:\n".$this->compactHtml($html),
        ]);

        $text = $this->qwen->chat($system, $user, (int) config('services.local_ai.code_tokens', 3000), 0.3);
        $files = $this->parseFiles($text, $expected, $framework['fence']);

        if ($files === []) {
            throw new RuntimeException("Qwen did not return {$framework['name']} code for the {$screen['name']} screen.");
        }

        // A stylesheet the model skipped is rebuilt from the design's own <style>,
        // so imports like "./Dashboard.css" never point at a missing file.
        $written = array_column($files, 'path');
        foreach ($expected as $path) {
            if (! in_array($path, $written, true) && preg_match('/\.(css|scss)$/', $path)) {
                preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $styles);
                $files[] = ['path' => $path, 'language' => 'css', 'content' => '/* '.$screen['name'].' styles, taken from the design preview */'."\n".trim(implode("\n\n", $styles[1] ?? []))."\n"];
            }
        }

        return array_map(fn ($f) => $this->tidy($f) + ['screen' => $screen['slug']], $files);
    }

    /** Small models often keep HTML attribute names in JSX; React wants className / htmlFor. */
    private function tidy(array $file): array
    {
        if (preg_match('/\.(jsx|tsx)$/', $file['path'])) {
            $file['content'] = preg_replace(['/(<[A-Za-z][^<>]*?\s)class=/', '/(<[A-Za-z][^<>]*?\s)for=/'], ['$1className=', '$1htmlFor='], $file['content']) ?? $file['content'];
        }

        return $file;
    }

    // ---------------------------------------------------------------- parsing

    /** @return array<int, array{path: string, language: string, content: string}> */
    public function parseFiles(string $text, array $expected, string $fence = ''): array
    {
        $files = [];

        // "### FILE: path" followed by a fenced block (the last block may be cut off).
        $labelled = (bool) preg_match('/^[ \t]*(?:#{1,4}[ \t]*)?(?:\*\*)?FILE(?:\*\*)?[ \t]*:/mi', $text);
        if (preg_match_all('/^[ \t]*(?:#{1,4}[ \t]*)?(?:\*\*)?FILE(?:\*\*)?[ \t]*:[ \t]*`?([^\n`*]+?)`?(?:\*\*)?[ \t]*\n+```([\w+#.-]*)[^\n]*\n(.*?)(?=\n```|\z)/msi', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $path = $this->cleanPath($hit[1]);
                if ($path !== null && trim($hit[3]) !== '') {
                    $files[$path] = ['path' => $path, 'language' => $this->langFor($path, $hit[2] ?: $fence), 'content' => rtrim($hit[3])."\n"];
                }
            }
        }

        // No headers: hand the plain code blocks to the expected paths in order.
        // (Labelled files whose paths were rejected stay rejected.)
        if ($files === [] && ! $labelled && preg_match_all('/```([\w+#.-]*)[^\n]*\n(.*?)(?=\n```|\z)/s', $text, $blocks, PREG_SET_ORDER)) {
            $blocks = array_values(array_filter($blocks, fn ($b) => trim($b[2]) !== ''));
            foreach ($blocks as $i => $block) {
                $path = $expected[$i] ?? ($expected ? null : 'src/screen-'.($i + 1).'.'.($block[1] ?: 'txt'));
                if ($path !== null) {
                    $files[$path] = ['path' => $path, 'language' => $this->langFor($path, $block[1] ?: $fence), 'content' => rtrim($block[2])."\n"];
                }
            }
        }

        // A bare answer with no fences at all still counts as the main file.
        if ($files === [] && $expected && trim($text) !== '' && ! str_contains($text, '```')) {
            $files[$expected[0]] = ['path' => $expected[0], 'language' => $this->langFor($expected[0], $fence), 'content' => trim($text)."\n"];
        }

        return array_values($files);
    }

    private function cleanPath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path), " \t*/");
        $path = preg_replace('#/+#', '/', $path) ?? '';
        $path = preg_replace('#^\./#', '', $path) ?? '';
        if ($path === '' || str_contains($path, '..') || ! preg_match('#^[A-Za-z0-9_\-./@\[\]()+ ]{1,160}$#', $path) || ! str_contains(basename($path), '.')) {
            return null;
        }

        return $path;
    }

    private function langFor(string $path, string $fallback): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jsx', 'js', 'mjs' => 'javascript', 'tsx', 'ts' => 'typescript', 'vue' => 'vue', 'css', 'scss' => 'css',
            'html', 'htm' => 'html', 'php' => 'php', 'py' => 'python', 'dart' => 'dart', 'kt' => 'kotlin',
            'swift' => 'swift', 'json' => 'json', 'md' => 'markdown', 'yaml', 'yml' => 'yaml', 'xml' => 'xml',
            default => $fallback ?: 'text',
        };
    }

    public function extractHtml(string $text): ?string
    {
        $start = stripos($text, '<!DOCTYPE');
        if ($start === false) {
            $start = stripos($text, '<html');
        }
        if ($start === false) {
            return null;
        }
        $end = strripos($text, '</html>');
        $html = $end === false ? substr($text, $start) : substr($text, $start, $end - $start + 7);
        $html = trim(preg_replace('/\s*```\s*$/', '', trim($html)) ?? $html);

        // Small models sometimes loop on a base64 "image" until the tokens run out.
        // Drop those blobs; if one was cut off, end the page where it started.
        if (preg_match('/data:[a-z0-9.+\/-]+;base64,[A-Za-z0-9+\/=\s]{200,}/i', $html, $blob, PREG_OFFSET_CAPTURE)) {
            $html = preg_replace('/(<img\b[^>]*?)\ssrc="data:[^"]*"([^>]*>)/i', '$1$2', $html) ?? $html;
            $html = preg_replace('/url\(\s*[\'"]?data:[^)]*\)/i', 'none', $html) ?? $html;
            if (preg_match('/data:[a-z0-9.+\/-]+;base64,/i', $html, $left, PREG_OFFSET_CAPTURE)) {
                $cut = strrpos(substr($html, 0, $left[0][1]), '<');
                $html = rtrim(substr($html, 0, $cut === false ? $left[0][1] : $cut));
            }
        }

        // A small local model can run out of tokens mid-page: close it so it still renders.
        if (! str_contains($html, '</body>')) {
            $html .= "\n</body>\n</html>";
        } elseif (! str_contains($html, '</html>')) {
            $html .= "\n</html>";
        }

        return $html;
    }

    /** @return array<string, mixed> */
    private function json(string $text): array
    {
        $text = preg_replace('/^```(?:json)?|```$/m', '', $text) ?? $text;
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false) {
            return [];
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($data) ? $data : [];
    }

    /** Shorter design for the code step: comments gone, long SVG paths trimmed, whitespace collapsed. */
    private function compactHtml(string $html): string
    {
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
        $html = preg_replace('/\sd="[^"]{120,}"/', ' d="M0 0"', $html) ?? $html;
        $html = preg_replace('/[ \t]+/', ' ', $html) ?? $html;
        $html = preg_replace("/\n\s*\n+/", "\n", $html) ?? $html;

        return mb_substr($html, 0, (int) config('services.local_ai.code_input_chars', 9000));
    }

    /** @param  array<int, array<string, mixed>>  $files */
    private function uniquePaths(array $files): array
    {
        $seen = [];
        foreach ($files as $file) {
            $seen[$file['path']] = $file;
        }

        return array_values($seen);
    }

    /** Stops quietly when the user deleted the generation mid-run. */
    private function ensureAlive(UiGeneration $generation): void
    {
        if (! UiGeneration::whereKey($generation->id)->exists()) {
            throw new GenerationCancelled('Generation deleted.');
        }
    }
}
