<?php

namespace App\Services\Generator;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Looks up real UI work on the web before a mockup is designed, so a brief
 * like "plumbing service website" is grounded in how those sites actually
 * look instead of one fixed internal template.
 *
 * Uses a keyless HTML search endpoint (no API key), caches results for a few hours and
 * falls back to a curated reference board when the network is unavailable.
 */
class InspirationScout
{
    /** Words that add nothing to a design search. */
    private const STOP = [
        'a', 'an', 'the', 'for', 'with', 'and', 'or', 'to', 'of', 'in', 'on', 'my', 'our', 'we', 'i',
        'app', 'website', 'site', 'page', 'web', 'mobile', 'design', 'ui', 'ux', 'want', 'need', 'make',
        'build', 'create', 'please', 'that', 'this', 'is', 'are', 'be', 'it', 'like', 'should', 'very',
        'modern', 'clean', 'simple', 'nice', 'good', 'new', 'using', 'use', 'some', 'can', 'you', 'me',
    ];

    /** Always-useful galleries, used when search is unreachable. */
    private const CURATED = [
        ['title' => 'Land-book — landing page gallery', 'url' => 'https://land-book.com/', 'source' => 'land-book.com'],
        ['title' => 'Lapa Ninja — free landing page inspiration', 'url' => 'https://www.lapa.ninja/', 'source' => 'lapa.ninja'],
        ['title' => 'Mobbin — mobile & web app screens', 'url' => 'https://mobbin.com/', 'source' => 'mobbin.com'],
        ['title' => 'Dribbble — web design shots', 'url' => 'https://dribbble.com/tags/web-design', 'source' => 'dribbble.com'],
        ['title' => 'Godly — astronomically good web design', 'url' => 'https://godly.website/', 'source' => 'godly.website'],
        ['title' => 'Wix template gallery', 'url' => 'https://www.wix.com/website/templates', 'source' => 'wix.com'],
        ['title' => 'SaaS Landing Page — teardowns', 'url' => 'https://saaslandingpage.com/', 'source' => 'saaslandingpage.com'],
    ];

    /**
     * @return array{query: string, source: string, references: array<int, array{title: string, url: string, source: string}>}
     */
    public function research(string $prompt, string $platform = 'web', string $style = 'modern'): array
    {
        if (! config('services.inspiration.enabled', true)) {
            return ['query' => '', 'source' => 'offline', 'references' => self::CURATED];
        }

        $query = $this->query($prompt, $platform);
        $key = 'inspiration:'.md5($query);

        try {
            $cached = Cache::get($key);
            if (is_array($cached) && $cached !== []) {
                return ['query' => $query, 'source' => 'cache', 'references' => $cached];
            }
        } catch (Throwable) {
            // Cache store unavailable (or not migrated yet) — keep going.
        }

        [$references, $source] = $this->search($query);

        if ($references === []) {
            return ['query' => $query, 'source' => 'curated', 'references' => self::CURATED];
        }

        try {
            Cache::put($key, $references, now()->addHours(6));
        } catch (Throwable) {
            // Caching is a nicety, not a requirement.
        }

        return ['query' => $query, 'source' => $source, 'references' => $references];
    }

    /**
     * Research for a multi-screen project: the search phrase comes from the
     * domains and screens the user picked plus the brief, and the top pages
     * are opened to collect concrete interface ideas for the model.
     *
     * @param  array<int, string>  $screens  @param  array<int, string>  $domains
     * @return array{query: string, source: string, references: array<int, array<string, mixed>>, ideas: string}
     */
    public function researchProject(string $prompt, string $platform, array $screens, array $domains): array
    {
        $focus = trim(implode(' ', array_slice($domains, 0, 2)).' '.implode(' ', array_slice($screens, 0, 2)));
        $board = $this->research(trim($focus.' '.$prompt), $platform);
        $board['ideas'] = $board['source'] === 'live' || $board['source'] === 'cache'
            ? $this->study($board['references'])
            : '';

        return $board;
    }

    /**
     * Opens a few reference pages and writes down what they show: headings,
     * navigation, calls to action, colours and fonts. Pages are read, never
     * copied: the notes go to the model as inspiration only.
     *
     * @param  array<int, array<string, mixed>>  $references
     */
    public function study(array &$references, int $pages = 3): string
    {
        $notes = [];

        foreach (array_slice(array_keys($references), 0, $pages) as $i) {
            $url = (string) ($references[$i]['url'] ?? '');
            $key = 'inspiration:page:'.md5($url);

            try {
                $found = Cache::get($key);
            } catch (Throwable) {
                $found = null;
            }

            if (! is_array($found)) {
                $found = $this->read($url);
                try {
                    Cache::put($key, $found, now()->addHours(12));
                } catch (Throwable) {
                }
            }

            if ($found === []) {
                continue;
            }

            $references[$i]['ideas'] = $found;
            $line = '- '.($references[$i]['source'] ?? $url).':';
            foreach (['headings' => 'headings', 'nav' => 'navigation', 'actions' => 'buttons', 'colors' => 'colours', 'fonts' => 'fonts'] as $field => $label) {
                if (! empty($found[$field])) {
                    $line .= " {$label}: ".implode(', ', $found[$field]).';';
                }
            }
            $notes[] = $line;
        }

        return implode("\n", $notes);
    }

    /** @return array<string, array<int, string>> */
    private function read(string $url): array
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with($url, 'http')) {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                'Accept' => 'text/html',
            ])->connectTimeout(4)->timeout(8)->get($url);

            if (! $response->successful() || ! str_contains(strtolower($response->header('Content-Type')), 'html')) {
                return [];
            }
            $html = substr($response->body(), 0, 400_000);
        } catch (Throwable) {
            return [];
        }

        $text = fn (string $s) => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5)) ?? '');
        $pick = function (string $pattern, int $limit, int $max = 60) use ($html, $text) {
            preg_match_all($pattern, $html, $m);
            $out = [];
            foreach ($m[1] ?? [] as $raw) {
                $t = $text($raw);
                if ($t !== '' && mb_strlen($t) <= $max && ! in_array($t, $out, true)) {
                    $out[] = $t;
                }
                if (count($out) >= $limit) {
                    break;
                }
            }

            return $out;
        };

        $nav = [];
        if (preg_match('/<nav\b.*?<\/nav>/is', $html, $navBlock)) {
            preg_match_all('/<a\b[^>]*>(.*?)<\/a>/is', $navBlock[0], $links);
            foreach ($links[1] as $raw) {
                $t = $text($raw);
                if ($t !== '' && mb_strlen($t) <= 24 && ! in_array($t, $nav, true)) {
                    $nav[] = $t;
                }
                if (count($nav) >= 8) {
                    break;
                }
            }
        }

        preg_match_all('/#([0-9a-f]{6})\b/i', $html, $hex);
        $colors = collect($hex[1] ?? [])->map(fn ($c) => '#'.strtolower($c))
            ->reject(fn ($c) => in_array($c, ['#ffffff', '#000000'], true))
            ->countBy()->sortDesc()->keys()->take(5)->values()->all();

        preg_match_all('/font-family\s*:\s*[\'"]?([A-Za-z][A-Za-z0-9 ]{2,30})/i', $html, $fonts);
        $fonts = collect($fonts[1] ?? [])->map(fn ($f) => trim($f))
            ->reject(fn ($f) => in_array(strtolower($f), ['inherit', 'sans serif', 'serif', 'system ui', 'arial', 'helvetica', 'monospace'], true))
            ->unique()->take(3)->values()->all();

        return array_filter([
            'title' => array_filter([$text(preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $t) ? $t[1] : '')]),
            'headings' => $pick('/<h[1-3]\b[^>]*>(.*?)<\/h[1-3]>/is', 6, 80),
            'nav' => $nav,
            'actions' => $pick('/<(?:button|a)\b[^>]*class="[^"]*(?:btn|button|cta)[^"]*"[^>]*>(.*?)<\/(?:button|a)>/is', 5, 30),
            'colors' => $colors,
            'fonts' => $fonts,
        ]);
    }

    /** Search phrasing built from the brief's own words, so it follows the prompt. */
    public function query(string $prompt, string $platform = 'web'): string
    {
        $words = preg_split('/[^a-z0-9+#]+/', mb_strtolower($prompt), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter($words, fn ($w) => mb_strlen($w) > 2 && ! in_array($w, self::STOP, true)));
        $words = array_slice(array_unique($words), 0, 6);

        $kind = $platform === 'mobile' ? 'app' : 'website';

        return trim(implode(' ', $words)." {$kind} ui design inspiration");
    }

    /**
     * Keyless web search (no API key, no paid service): DuckDuckGo's HTML
     * endpoint first, its lite endpoint as a second try.
     *
     * @return array{0: array<int, array{title: string, url: string, source: string}>, 1: string}
     */
    private function search(string $query): array
    {
        $results = $this->searchDuckDuckGo($query);

        if ($results === []) {
            $results = $this->searchDuckDuckGo($query, 'https://lite.duckduckgo.com/lite/');
        }

        return [$results, $results === [] ? 'curated' : 'live'];
    }

    /**
     * @return array<int, array{title: string, url: string, source: string}>
     */
    private function searchDuckDuckGo(string $query, ?string $endpoint = null): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
                ->connectTimeout((int) config('services.inspiration.connect_timeout', 4))
                ->timeout((int) config('services.inspiration.timeout', 8))
                ->get($endpoint ?? (string) config('services.inspiration.endpoint', 'https://html.duckduckgo.com/html/'), ['q' => $query]);

            if (! $response->successful()) {
                Log::info('UI inspiration search returned '.$response->status());

                return [];
            }

            return $this->parse($response->body());
        } catch (Throwable $e) {
            Log::info('UI inspiration search failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array<int, array{title: string, url: string, source: string}>
     */
    private function parse(string $html): array
    {
        $found = [];

        if (! preg_match_all('/<a[^>]*class="[^"]*result__a[^"]*"[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', $html, $rows, PREG_SET_ORDER)
            && ! preg_match_all('/<a[^>]*href="([^"]+)"[^>]*class=[\'"][^\'"]*result-link[^\'"]*[\'"][^>]*>(.*?)<\/a>/is', $html, $rows, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($rows as $row) {
            $url = $this->resolveUrl(html_entity_decode($row[1], ENT_QUOTES));
            $title = trim(html_entity_decode(strip_tags($row[2]), ENT_QUOTES));

            if ($url === null || $title === '' || isset($found[$url]) || $this->isNoise($url)) {
                continue;
            }

            $found[$url] = ['title' => $title, 'url' => $url, 'source' => (string) parse_url($url, PHP_URL_HOST)];

            if (count($found) >= 6) {
                break;
            }
        }

        return array_values($found);
    }

    /** DuckDuckGo wraps results as /l/?uddg=<encoded target>. */
    private function resolveUrl(string $href): ?string
    {
        if (str_starts_with($href, '//')) {
            $href = 'https:'.$href;
        }

        if (! str_starts_with($href, 'http')) {
            return null;
        }

        $host = parse_url($href, PHP_URL_HOST);
        if ($host && str_contains($host, 'duckduckgo.com')) {
            parse_str((string) parse_url($href, PHP_URL_QUERY), $params);
            $href = isset($params['uddg']) ? (string) $params['uddg'] : '';
        }

        if (! str_starts_with($href, 'http')) {
            return null;
        }

        return filter_var($href, FILTER_VALIDATE_URL) ? $href : null;
    }

    private function isNoise(string $url): bool
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach (['duckduckgo', 'google.', 'bing.', 'youtube.', 'pinterest.', 'facebook.', 'instagram.', 'quora.', 'reddit.'] as $skip) {
            if (str_contains($host, $skip)) {
                return true;
            }
        }

        return false;
    }
}
