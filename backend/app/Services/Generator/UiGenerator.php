<?php

namespace App\Services\Generator;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates a single-file HTML mockup for a prompt.
 *
 * Every run starts with a quick web lookup for real UI references and then
 * picks an art direction (typography, palette, layout recipe) from that
 * research. The local Qwen model or Claude designs the page when available;
 * otherwise TemplateEngine composes the same direction offline. Either way a
 * new prompt — or the same prompt twice — lands on a different design.
 */
class UiGenerator
{
    public function __construct(
        private TemplateEngine $templates,
        private InspirationScout $inspiration,
    ) {}

    /**
     * @return array{html: string, provider: string, notice: ?string, design: array<string, mixed>}
     */
    public function generate(string $prompt, string $platform, string $screenType, string $style, ?string $accent, ?string $categorySlug, bool $preferAi = true): array
    {
        $localBaseUrl = config('services.local_ai.base_url');
        $anthropicKey = config('services.anthropic.key');

        // 1. Research: how do products like this actually look right now?
        $research = $this->inspiration->research($prompt, $platform, $style);

        // 2. Direction: turn the brief + research into a concrete design recipe.
        $profile = DomainProfiles::forPrompt($prompt, $categorySlug);
        $direction = DesignDirection::pick($prompt, $style, $accent, $profile['key'], $research['references']);

        $notice = null;

        if ($preferAi && $localBaseUrl) {
            try {
                $html = $this->viaLocalAi($localBaseUrl, $prompt, $platform, $screenType, $style, $accent, $direction, $research);

                return ['html' => $html, 'provider' => 'local', 'notice' => null, 'design' => $this->designPayload($direction, $research)];
            } catch (Throwable $e) {
                Log::warning('Local AI UI generation failed, using templates', ['error' => $e->getMessage()]);
                $notice = 'The local Qwen model was unavailable, so this mockup was composed by the built-in layout engine.';
            }
        } elseif ($preferAi && $anthropicKey) {
            try {
                $html = $this->viaClaude($anthropicKey, $prompt, $platform, $screenType, $style, $accent, $direction, $research);

                return ['html' => $html, 'provider' => 'anthropic', 'notice' => null, 'design' => $this->designPayload($direction, $research)];
            } catch (Throwable $e) {
                Log::warning('AI UI generation failed, using templates', ['error' => $e->getMessage()]);
                $notice = 'The AI service was unavailable, so this mockup was composed by the built-in layout engine.';
            }
        }

        return [
            'html' => $this->templates->render($prompt, $platform, $screenType, $style, $accent, $categorySlug, $direction),
            'provider' => 'template',
            'notice' => $notice,
            'design' => $this->designPayload($direction, $research),
        ];
    }

    /** @param  array<string, mixed>  $direction  @param  array<string, mixed>  $research */
    private function designPayload(array $direction, array $research): array
    {
        return [
            'direction' => $direction['label'],
            'language' => $direction['language'],
            'summary' => DesignDirection::summary($direction),
            'accent' => $direction['accent'] ?? null,
            'recipe' => [
                'hero' => $direction['hero'],
                'proof' => $direction['proof'],
                'features' => $direction['features'],
                'showcase' => $direction['showcase'],
                'closing' => $direction['cta'],
                'order' => $direction['showcaseFirst'] ? 'work first' : 'benefits first',
            ],
            'research' => [
                'source' => $research['source'],
                'query' => $research['query'],
                'references' => $research['references'],
            ],
        ];
    }

    /** Design brief handed to a model, including the reference board from research. */
    private function brief(array $direction, array $research): string
    {
        $references = '';
        foreach (array_slice($research['references'], 0, 5) as $ref) {
            $references .= "- {$ref['title']} ({$ref['source']})\n";
        }

        return implode("\n", [
            'Art direction: '.$direction['label'].'.',
            "Display type: {$direction['display']}. Body type: {$direction['body']}. Accent: ".($direction['accent'] ?? 'match the domain').". Corner radius: {$direction['radius']}.",
            "Layout recipe: {$direction['hero']} hero, {$direction['proof']} social proof, {$direction['features']} benefits, {$direction['showcase']} showcase, {$direction['cta']} closing CTA, content order: ".($direction['showcaseFirst'] ? 'show the work before the explanation' : 'explain the benefits before the work').'.',
            $references === '' ? '' : "Reference board from a web search for \"{$research['query']}\" (borrow patterns, never copy a site or brand):\n".$references,
        ]);
    }

    private function viaLocalAi(string $baseUrl, string $prompt, string $platform, string $screenType, string $style, ?string $accent, array $direction, array $research): string
    {
        @set_time_limit(0);

        $system = $this->systemPrompt();

        $user = $this->userPrompt($prompt, $platform, $screenType, $style, $accent)
            ."\n\n".$this->brief($direction, $research);

        $endpoint = rtrim($baseUrl, '/').'/chat/completions';
        $timeout = (int) config('services.local_ai.timeout', 180);
        $model = config('services.local_ai.model', 'qwen2.5-coder-7b-instruct');

        $response = Http::timeout($timeout)->post($endpoint, [
            'model' => $model,
            'temperature' => 0.7,
            'top_p' => 0.9,
            // Small local models run on CPU, so keep the page compact enough to
            // finish inside the request timeout instead of being cut off.
            'max_tokens' => (int) config('services.local_ai.max_tokens', 900),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ])->throw();

        $text = $response->json('choices.0.message.content', '');
        $html = $this->extractHtml($text);

        if ($html === null) {
            throw new \RuntimeException('Model response did not contain an HTML document.');
        }

        return $html;
    }

    private function viaClaude(string $key, string $prompt, string $platform, string $screenType, string $style, ?string $accent, array $direction, array $research): string
    {
        $system = $this->systemPrompt();

        $user = $this->userPrompt($prompt, $platform, $screenType, $style, $accent)
            ."\n\n".$this->brief($direction, $research);

        $response = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model'),
            'max_tokens' => config('services.anthropic.max_tokens'),
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $user]],
        ])->throw();

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        $html = $this->extractHtml($text);
        if ($html === null) {
            throw new \RuntimeException('Model response did not contain an HTML document.');
        }

        return $html;
    }

    private function systemPrompt(): string
    {
        return <<<'SYS'
You are a senior product designer who writes production-quality HTML/CSS mockups.
Return ONLY a complete HTML document, starting with <!DOCTYPE html>. No markdown fences, no commentary.
Rules:
- One self-contained file. Put all CSS in a <style> tag. You may load Google Fonts; nothing else external.
- No external images: use CSS gradients, emoji or inline SVG for imagery and avatars.
- Follow the product brief literally: a plumbing service gets plumbing services, prices and callout areas, not a generic SaaS layout. Every section must be about what the brief describes.
- Invent original, specific copy and data (service names, prices, reviews, area names). No lorem ipsum, no filler sentences.
- Vary the structure to fit the art direction: a split hero is not a centred hero, an editorial page leads with type, a product page leads with the work.
- Clear visual hierarchy, consistent spacing scale, accessible contrast, responsive grid that still looks right at 1280px.
- Static mockup: no JavaScript behaviour.
- Keep the document compact: one strong screen, at most 6 sections, no repeated blocks. Finish the closing </html> tag.
- Never reproduce a real company's brand, logo or trademarked name; invent an original brand.
SYS;
    }

    private function userPrompt(string $prompt, string $platform, string $screenType, string $style, ?string $accent): string
    {
        $viewport = match ($platform) {
            'mobile' => 'a mobile app screen designed for a 390px-wide phone viewport (include a status bar and, where it fits, a bottom tab bar)',
            'tablet' => 'a tablet screen designed for an 820px-wide viewport',
            default => 'a desktop website page designed for a 1280px-wide viewport',
        };
        $accentLine = $accent ? "Use {$accent} as the primary accent color." : 'Pick an accent color that suits the domain.';

        return "Design {$viewport}.\nScreen type: {$screenType}.\nVisual style: {$style}.\n{$accentLine}\nProduct / topic: {$prompt}";
    }

    private function extractHtml(string $text): ?string
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

        $html = trim(preg_replace('/^```(?:html)?\s*|\s*```$/i', '', trim($html)));

        // A small local model can run out of tokens mid-document; close the
        // tags so the browser still renders a complete page.
        if (! str_contains($html, '</body>')) {
            $html .= "\n</body>\n</html>";
        }

        return $html;
    }
}
