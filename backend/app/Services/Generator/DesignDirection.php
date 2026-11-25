<?php

namespace App\Services\Generator;

/**
 * Picks a concrete art direction for one generation: typography, radius,
 * density, hero treatment, section order and small ornaments. The choice is
 * seeded, so the same brief keeps its identity, while the random salt makes
 * every new generation a different take instead of a fixed template.
 */
class DesignDirection
{
    /**
     * Design languages borrowed from how good product sites actually look.
     * Each one names the font pairing, the shapes and the layouts it favours.
     */
    public const LANGUAGES = [
        'editorial' => [
            'label' => 'Editorial',
            'display' => '"Fraunces", Georgia, "Times New Roman", serif',
            'body' => '"Inter", system-ui, sans-serif',
            'google' => 'Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600',
            'headWeight' => 600,
            'headSpacing' => '-0.035em',
            'headTransform' => 'none',
            'headScale' => 1.12,
            'radius' => '4px',
            'tint' => '#fbf8f2',
            'ink' => '#1b1814',
            'ornament' => 'rules',
            'heroes' => ['editorial', 'split', 'centered'],
            'accents' => ['#1f3d2f', '#8a3b1f', '#22303f'],
        ],
        'swiss' => [
            'label' => 'Swiss grid',
            'display' => '"Space Grotesk", "Helvetica Neue", Arial, sans-serif',
            'body' => '"Inter", system-ui, sans-serif',
            'google' => 'Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600',
            'headWeight' => 600,
            'headSpacing' => '-0.045em',
            'headTransform' => 'none',
            'headScale' => 1.16,
            'radius' => '2px',
            'tint' => '#f4f4f1',
            'ink' => '#101010',
            'ornament' => 'grid',
            'heroes' => ['split', 'stat', 'bento'],
            'accents' => ['#1d4ed8', '#b3261e', '#0b6b4f'],
        ],
        'quiet' => [
            'label' => 'Quiet minimal',
            'display' => '"Instrument Serif", Georgia, serif',
            'body' => '"Inter", system-ui, sans-serif',
            'google' => 'Instrument+Serif&family=Inter:wght@400;500;600',
            'headWeight' => 400,
            'headSpacing' => '-0.02em',
            'headTransform' => 'none',
            'headScale' => 1.2,
            'radius' => '10px',
            'tint' => '#fcfcfb',
            'ink' => '#191919',
            'ornament' => 'none',
            'heroes' => ['centered', 'editorial'],
            'accents' => ['#3d5147', '#6b4f3a', '#2d4374'],
        ],
        'soft-saas' => [
            'label' => 'Soft product',
            'display' => '"Sora", "Inter", system-ui, sans-serif',
            'body' => '"Inter", system-ui, sans-serif',
            'google' => 'Sora:wght@500;600;700&family=Inter:wght@400;500;600',
            'headWeight' => 600,
            'headSpacing' => '-0.03em',
            'headTransform' => 'none',
            'headScale' => 1.05,
            'radius' => '18px',
            'tint' => '#f6f7fd',
            'ink' => '#141726',
            'ornament' => 'blobs',
            'heroes' => ['split', 'centered', 'bento'],
            'accents' => ['#4f46e5', '#0f766e', '#2563eb'],
        ],
        'console' => [
            'label' => 'Dark console',
            'display' => '"Sora", "Inter", system-ui, sans-serif',
            'body' => '"Inter", system-ui, sans-serif',
            'google' => 'Sora:wght@500;600;700&family=Inter:wght@400;500;600',
            'headWeight' => 600,
            'headSpacing' => '-0.03em',
            'headTransform' => 'none',
            'headScale' => 1.04,
            'radius' => '12px',
            'tint' => '#0b0d12',
            'ink' => '#eef1f7',
            'ornament' => 'grid',
            'heroes' => ['split', 'stat', 'centered'],
            'accents' => ['#7c9cff', '#5eead4', '#f0abfc'],
        ],
        'glass-neon' => [
            'label' => 'Glass neon',
            'display' => '"Sora", "Inter", system-ui, sans-serif',
            'body' => '"Inter", system-ui, sans-serif',
            'google' => 'Sora:wght@500;600;700&family=Inter:wght@400;500;600',
            'headWeight' => 600,
            'headSpacing' => '-0.03em',
            'headTransform' => 'none',
            'headScale' => 1.08,
            'radius' => '22px',
            'tint' => '#150f24',
            'ink' => '#ffffff',
            'ornament' => 'blobs',
            'heroes' => ['centered', 'bento', 'split'],
            'accents' => ['#ff7ad9', '#8b5cf6', '#22d3ee'],
        ],
        'pop' => [
            'label' => 'Playful pop',
            'display' => '"Bricolage Grotesque", "Trebuchet MS", sans-serif',
            'body' => '"Inter", system-ui, sans-serif',
            'google' => 'Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Inter:wght@400;500;600',
            'headWeight' => 700,
            'headSpacing' => '-0.03em',
            'headTransform' => 'none',
            'headScale' => 1.1,
            'radius' => '24px',
            'tint' => '#fff8ee',
            'ink' => '#241c3a',
            'ornament' => 'dots',
            'heroes' => ['centered', 'bento', 'split'],
            'accents' => ['#ff5c47', '#7c3aed', '#0ea5e9'],
        ],
        'craft' => [
            'label' => 'Warm craft',
            'display' => '"Newsreader", Georgia, serif',
            'body' => '"Inter", system-ui, sans-serif',
            'google' => 'Newsreader:opsz,wght@6..72,400;6..72,600&family=Inter:wght@400;500;600',
            'headWeight' => 500,
            'headSpacing' => '-0.025em',
            'headTransform' => 'none',
            'headScale' => 1.14,
            'radius' => '14px',
            'tint' => '#f7f2e9',
            'ink' => '#231d16',
            'ornament' => 'rules',
            'heroes' => ['editorial', 'split', 'centered'],
            'accents' => ['#2f5d50', '#9a4b22', '#3c4a63'],
        ],
    ];

    /** Which languages suit each visual style picker option. */
    private const BY_STYLE = [
        'modern' => ['soft-saas', 'swiss', 'editorial', 'quiet'],
        'minimal' => ['quiet', 'swiss', 'editorial'],
        'dark' => ['console', 'glass-neon'],
        'playful' => ['pop', 'craft'],
        'corporate' => ['swiss', 'soft-saas', 'editorial'],
        'glass' => ['glass-neon', 'console', 'pop'],
    ];

    /** Structural choices that make two mockups of the same brief differ. */
    private const VARIANTS = [
        'proof' => ['stats', 'logos', 'quote', 'stats', 'none'],
        'features' => ['cards', 'bento', 'rows', 'split-list'],
        'showcase' => ['grid', 'rows', 'pricelist', 'bento'],
        'extra' => ['testimonial', 'faq', 'process', 'testimonial'],
        'cta' => ['band', 'split', 'ticket'],
        'footer' => ['columns', 'simple'],
        'visual' => ['panel', 'tiles', 'chart', 'phone'],
    ];

    /**
     * @param  array<int, array{title: string, url: string, source: string}>  $references
     * @return array<string, mixed>
     */
    public static function pick(string $prompt, string $style, ?string $accent, string $domain = 'generic', array $references = []): array
    {
        $seed = (int) (crc32($prompt.'|'.$style.'|'.$domain.'|'.random_int(1000, 999999)) % 2_000_000_000);
        mt_srand($seed);

        $candidates = self::BY_STYLE[$style] ?? array_keys(self::LANGUAGES);
        $key = $candidates[array_rand($candidates)];
        $language = self::LANGUAGES[$key];

        $hero = $language['heroes'][array_rand($language['heroes'])];
        $direction = [
            'language' => $key,
            'label' => $language['label'],
            'display' => $language['display'],
            'body' => $language['body'],
            'google' => $language['google'],
            'headWeight' => $language['headWeight'],
            'headSpacing' => $language['headSpacing'],
            'headTransform' => $language['headTransform'],
            'headScale' => $language['headScale'],
            'radius' => $language['radius'],
            'tint' => $language['tint'],
            'ink' => $language['ink'],
            'ornament' => $language['ornament'],
            'hero' => $hero,
            'proof' => self::pickFrom(self::VARIANTS['proof']),
            'features' => self::pickFrom(self::VARIANTS['features']),
            'showcase' => self::pickFrom(self::VARIANTS['showcase']),
            'extra' => self::pickFrom(self::VARIANTS['extra']),
            'cta' => self::pickFrom(self::VARIANTS['cta']),
            'footer' => self::pickFrom(self::VARIANTS['footer']),
            'visual' => self::pickFrom(self::VARIANTS['visual']),
            'seed' => $seed,
            'references' => array_slice($references, 0, 4),
        ];

        // No accent from the user: the direction owns the palette accent,
        // sometimes nudged by the reference board so two runs feel distinct.
        if (! $accent || ! preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
            $direction['accent'] = $language['accents'][array_rand($language['accents'])];
        }

        // Show the content before the explanation about half the time — the way
        // real portfolio and service sites often lead with the work.
        $direction['showcaseFirst'] = (bool) random_int(0, 1);
        $direction['theme'] = $style;

        return $direction;
    }

    private static function pickFrom(array $list): string
    {
        return $list[array_rand($list)];
    }

    /** Short human summary shown next to a generated mockup. */
    public static function summary(array $direction): string
    {
        return implode(' · ', array_filter([
            $direction['label'],
            $direction['hero'].' hero',
            $direction['features'].' benefits',
            $direction['showcase'].' showcase',
        ]));
    }
}
