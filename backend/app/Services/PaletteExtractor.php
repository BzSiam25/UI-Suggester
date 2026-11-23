<?php

namespace App\Services;

/**
 * Extracts the dominant colors of a UI screenshot using GD.
 *
 * The image is shrunk to a small sample, every pixel is snapped into a
 * coarse RGB bucket, the busiest buckets are averaged back into real
 * colors, and near-duplicates are merged so the palette stays distinct.
 */
class PaletteExtractor
{
    public function __construct(
        private int $sampleSize = 96,
        private int $bucketBits = 4,      // 16 levels per channel
        private int $maxColors = 6,
        private float $minDistance = 38.0, // RGB distance below which colors merge
    ) {}

    /**
     * @return array<int, array{hex: string, percent: float}>
     */
    public function extract(string $path): array
    {
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if (! $src) {
            return [];
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $this->sampleSize / max($w, $h));
        $sw = max(1, (int) round($w * $scale));
        $sh = max(1, (int) round($h * $scale));

        $sample = imagecreatetruecolor($sw, $sh);
        imagecopyresampled($sample, $src, 0, 0, 0, 0, $sw, $sh, $w, $h);
        imagedestroy($src);

        $shift = 8 - $this->bucketBits;
        $buckets = [];
        $total = 0;

        for ($y = 0; $y < $sh; $y++) {
            for ($x = 0; $x < $sw; $x++) {
                $rgba = imagecolorat($sample, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha > 100) {
                    continue; // mostly transparent
                }
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $key = (($r >> $shift) << 16) | (($g >> $shift) << 8) | ($b >> $shift);

                $buckets[$key] ??= [0, 0, 0, 0];
                $buckets[$key][0] += $r;
                $buckets[$key][1] += $g;
                $buckets[$key][2] += $b;
                $buckets[$key][3]++;
                $total++;
            }
        }
        imagedestroy($sample);

        if ($total === 0) {
            return [];
        }

        usort($buckets, fn ($a, $b) => $b[3] <=> $a[3]);

        /** @var array<int, array{rgb: int[], count: int}> $picked */
        $picked = [];
        foreach ($buckets as [$r, $g, $b, $count]) {
            $rgb = [(int) round($r / $count), (int) round($g / $count), (int) round($b / $count)];

            $merged = false;
            foreach ($picked as &$p) {
                if ($this->distance($p['rgb'], $rgb) < $this->minDistance) {
                    $p['count'] += $count;
                    $merged = true;
                    break;
                }
            }
            unset($p);

            if (! $merged) {
                if (count($picked) >= $this->maxColors) {
                    continue;
                }
                $picked[] = ['rgb' => $rgb, 'count' => $count];
            }
        }

        usort($picked, fn ($a, $b) => $b['count'] <=> $a['count']);

        $palette = array_map(fn ($p) => [
            'hex' => sprintf('#%02x%02x%02x', ...$p['rgb']),
            'percent' => round($p['count'] / $total * 100, 1),
        ], $picked);

        // Drop specks (anti-aliasing, tiny icons) that aren't really part of the palette.
        return array_values(array_filter($palette, fn ($c) => $c['percent'] >= 0.5));
    }

    /**
     * Name the color families present in a palette (colors covering >= 4%),
     * e.g. "white blue gray", so the gallery can be filtered by color.
     */
    public static function families(array $palette): string
    {
        $names = [];
        foreach ($palette as $c) {
            $pct = $c['percent'] ?? 0;
            $family = self::family($c['hex']);
            $neutral = in_array($family, ['black', 'white', 'gray'], true);
            // Neutrals need real coverage; accent colors count even when small.
            if ($pct >= 4 || (! $neutral && $pct >= 0.8)) {
                $names[] = $family;
            }
        }

        return implode(' ', array_unique($names));
    }

    public static function family(string $hex): string
    {
        [$r, $g, $b] = array_map(fn ($x) => hexdec($x) / 255, str_split(ltrim($hex, '#'), 2));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        $s = $d == 0 ? 0 : $d / (1 - abs(2 * $l - 1));

        if ($l < 0.15) {
            return 'black';
        }
        if ($l > 0.92) {
            return 'white';
        }
        if ($s < 0.15) {
            return 'gray';
        }

        $h = match (true) {
            $max == $r => 60 * fmod(($g - $b) / $d, 6),
            $max == $g => 60 * (($b - $r) / $d + 2),
            default => 60 * (($r - $g) / $d + 4),
        };
        if ($h < 0) {
            $h += 360;
        }

        if (($h < 45 && $l < 0.4 && $s < 0.6) || ($h >= 15 && $h < 45 && $l < 0.35)) {
            return 'brown';
        }

        return match (true) {
            $h < 15 || $h >= 345 => 'red',
            $h < 45 => 'orange',
            $h < 70 => 'yellow',
            $h < 160 => 'green',
            $h < 195 => 'teal',
            $h < 255 => 'blue',
            $h < 290 => 'purple',
            default => 'pink',
        };
    }

    /** Weighted ("redmean") RGB distance — cheap and closer to perception than plain Euclid. */
    private function distance(array $a, array $b): float
    {
        $rMean = ($a[0] + $b[0]) / 2;
        $dr = $a[0] - $b[0];
        $dg = $a[1] - $b[1];
        $db = $a[2] - $b[2];

        return sqrt((2 + $rMean / 256) * $dr ** 2 + 4 * $dg ** 2 + (2 + (255 - $rMean) / 256) * $db ** 2) / 3;
    }
}
