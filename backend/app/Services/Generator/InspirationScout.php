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
 * Uses a keyless HTML search endpoint, caches results for a few hours and
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
     * Searches through a keyed API when one is configured (Exa is the default
     * provider), then a keyless HTML search endpoint as a fallback.
     *
     * @return array{0: array<int, array{title: string, url: string, source: string}>, 1: string}
     */
    private function search(string $query): array
    {
        $exaKey = (string) config('services.exa.key', '');

        if ($exaKey !== '') {
            $results = $this->searchExa($exaKey, $query);
            if ($results !== []) {
                return [$results, 'live'];
            }
        }

        $results = $this->searchDuckDuckGo($query);

        return [$results, $results === [] ? 'curated' : 'live'];
    }

    /**
     * @return array<int, array{title: string, url: string, source: string}>
     */
    private function searchExa(string $key, string $query): array
    {
        try {
            $response = Http::withHeaders(['x-api-key' => $key, 'Content-Type' => 'application/json'])
                ->connectTimeout((int) config('services.inspiration.connect_timeout', 4))
                ->timeout((int) config('services.inspiration.timeout', 8))
                ->post(rtrim((string) config('services.exa.base_url', 'https://api.exa.ai'), '/').'/search', [
                    'query' => $query,
                    'numResults' => 6,
                    'type' => 'auto',
                    'contents' => ['text' => false, 'highlights' => false],
                ]);

            if (! $response->successful()) {
                Log::info('Exa inspiration search returned '.$response->status());

                return [];
            }

            $references = [];
            foreach ($response->json('results', []) as $result) {
                $url = (string) ($result['url'] ?? '');
                if ($url === '' || $this->isNoise($url)) {
                    continue;
                }
                $references[] = [
                    'title' => trim((string) ($result['title'] ?? $url)),
                    'url' => $url,
                    'source' => (string) parse_url($url, PHP_URL_HOST),
                ];
            }

            return array_slice($references, 0, 6);
        } catch (Throwable $e) {
            Log::info('Exa inspiration search failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array<int, array{title: string, url: string, source: string}>
     */
    private function searchDuckDuckGo(string $query): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
                ->connectTimeout((int) config('services.inspiration.connect_timeout', 4))
                ->timeout((int) config('services.inspiration.timeout', 8))
                ->get((string) config('services.inspiration.endpoint', 'https://html.duckduckgo.com/html/'), ['q' => $query]);

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

        if (! preg_match_all('/<a[^>]*class="[^"]*result__a[^"]*"[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', $html, $rows, PREG_SET_ORDER)) {
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
