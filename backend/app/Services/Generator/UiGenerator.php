<?php

namespace App\Services\Generator;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates a single-file HTML mockup for a prompt.
 * Uses the Claude API when ANTHROPIC_API_KEY is set; otherwise (or if the
 * API call fails) falls back to the offline TemplateEngine.
 */
class UiGenerator
{
    public function __construct(private TemplateEngine $templates) {}

    /**
     * @return array{html: string, provider: string, notice: ?string}
     */
    public function generate(string $prompt, string $platform, string $screenType, string $style, ?string $accent, ?string $categorySlug, bool $preferAi = true): array
    {
        $localBaseUrl = config('services.local_ai.base_url');
        $anthropicKey = config('services.anthropic.key');

        if ($preferAi && $localBaseUrl) {
            try {
                return ['html' => $this->viaLocalAi($localBaseUrl, $prompt, $platform, $screenType, $style, $accent), 'provider' => 'local', 'notice' => null];
            } catch (Throwable $e) {
                Log::warning('Local AI UI generation failed, using templates', ['error' => $e->getMessage()]);
                $notice = 'The Local AI service (LM Studio) was unavailable, so this mockup was made with the built-in template engine.';
            }
        } elseif ($preferAi && $anthropicKey) {
            try {
                return ['html' => $this->viaClaude($anthropicKey, $prompt, $platform, $screenType, $style, $accent), 'provider' => 'anthropic', 'notice' => null];
            } catch (Throwable $e) {
                Log::warning('AI UI generation failed, using templates', ['error' => $e->getMessage()]);
                $notice = 'The AI service was unavailable, so this mockup was made with the built-in template engine.';
            }
        }

        return [
            'html' => $this->templates->render($prompt, $platform, $screenType, $style, $accent, $categorySlug),
            'provider' => 'template',
            'notice' => $notice ?? null,
        ];
    }

    private function viaLocalAi(string $baseUrl, string $prompt, string $platform, string $screenType, string $style, ?string $accent): string
    {
        @set_time_limit(0);

        $viewport = match ($platform) {
            'mobile' => 'a mobile app screen designed for a 390px-wide phone viewport (include a status bar and, where it fits, a bottom tab bar)',
            'tablet' => 'a tablet screen designed for an 820px-wide viewport',
            default => 'a desktop website page designed for a 1280px-wide viewport',
        };
        $accentLine = $accent ? "Use {$accent} as the primary accent color." : 'Pick an accent color that suits the domain.';

        $system = <<<'SYS'
You are a senior product designer who writes production-quality HTML/CSS mockups.
Return ONLY a complete HTML document, starting with <!DOCTYPE html>. No markdown fences, no commentary.
Rules:
- One self-contained file. Put all CSS in a <style> tag. You may load Tailwind via <script src="https://cdn.tailwindcss.com"></script> and Google Fonts; nothing else external.
- No external images: use CSS gradients, emoji or inline SVG for imagery and avatars.
- Realistic, domain-specific copy and data (names, prices, stats). No lorem ipsum.
- Clear visual hierarchy, consistent spacing scale, accessible contrast.
- Static mockup: no JavaScript behaviour beyond Tailwind.
- Never reproduce a real company's brand, logo or trademarked name; invent an original brand.
SYS;

        $user = "Design {$viewport}.\nScreen type: {$screenType}.\nVisual style: {$style}.\n{$accentLine}\nProduct / topic: {$prompt}";

        $endpoint = rtrim($baseUrl, '/') . '/chat/completions';
        $timeout = (int) config('services.local_ai.timeout', 180);
        $model = config('services.local_ai.model', 'qwen2.5-coder-7b-instruct');

        $response = Http::timeout($timeout)->post($endpoint, [
            'model' => $model,
            'temperature' => 0.3,
            'max_tokens' => 2500,
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

    private function viaClaude(string $key, string $prompt, string $platform, string $screenType, string $style, ?string $accent): string
    {
        $viewport = match ($platform) {
            'mobile' => 'a mobile app screen designed for a 390px-wide phone viewport (include a status bar and, where it fits, a bottom tab bar)',
            'tablet' => 'a tablet screen designed for an 820px-wide viewport',
            default => 'a desktop website page designed for a 1280px-wide viewport',
        };
        $accentLine = $accent ? "Use {$accent} as the primary accent color." : 'Pick an accent color that suits the domain.';

        $system = <<<'SYS'
You are a senior product designer who writes production-quality HTML/CSS mockups.
Return ONLY a complete HTML document, starting with <!DOCTYPE html>. No markdown fences, no commentary.
Rules:
- One self-contained file. Put all CSS in a <style> tag. You may load Tailwind via <script src="https://cdn.tailwindcss.com"></script> and Google Fonts; nothing else external.
- No external images: use CSS gradients, emoji or inline SVG for imagery and avatars.
- Realistic, domain-specific copy and data (names, prices, stats). No lorem ipsum.
- Clear visual hierarchy, consistent spacing scale, accessible contrast.
- Static mockup: no JavaScript behaviour beyond Tailwind.
- Never reproduce a real company's brand, logo or trademarked name; invent an original brand.
SYS;

        $user = "Design {$viewport}.\nScreen type: {$screenType}.\nVisual style: {$style}.\n{$accentLine}\nProduct / topic: {$prompt}";

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

        return trim(preg_replace('/^```(?:html)?\s*|\s*```$/i', '', trim($html)));
    }
}
