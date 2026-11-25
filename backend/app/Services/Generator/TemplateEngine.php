<?php

namespace App\Services\Generator;

/**
 * Offline UI mockup generator. Produces a self-contained HTML page (no CDN,
 * no external images) for a platform + screen type, filled with copy that
 * matches the prompt's domain and themed by the chosen visual style.
 */
class TemplateEngine
{
    public const STYLES = [
        'modern' => ['bg' => '#f7f7fb', 'surface' => '#ffffff', 'text' => '#15151f', 'muted' => '#6b6b7b', 'border' => '#e6e6ef', 'accent' => '#5b5bd6', 'radius' => '14px', 'font' => '"Inter", system-ui, sans-serif', 'hero' => 'linear-gradient(135deg,#eef0ff,#fdf2ff)'],
        'minimal' => ['bg' => '#ffffff', 'surface' => '#ffffff', 'text' => '#111111', 'muted' => '#707070', 'border' => '#e8e8e8', 'accent' => '#111111', 'radius' => '6px', 'font' => '"Helvetica Neue", Arial, sans-serif', 'hero' => '#ffffff'],
        'dark' => ['bg' => '#0d0f14', 'surface' => '#161a22', 'text' => '#eef0f5', 'muted' => '#8b93a7', 'border' => '#252b37', 'accent' => '#7c9cff', 'radius' => '12px', 'font' => '"Inter", system-ui, sans-serif', 'hero' => 'radial-gradient(circle at 20% 10%,#1e2a4a,#0d0f14 60%)'],
        'playful' => ['bg' => '#fff8ef', 'surface' => '#ffffff', 'text' => '#2a2140', 'muted' => '#7a6f8f', 'border' => '#f1e4d3', 'accent' => '#ff6b5b', 'radius' => '22px', 'font' => '"Nunito", "Trebuchet MS", sans-serif', 'hero' => 'linear-gradient(135deg,#ffe3d6,#e6f4ff)'],
        'corporate' => ['bg' => '#f4f6f9', 'surface' => '#ffffff', 'text' => '#13233a', 'muted' => '#5d6b80', 'border' => '#dde3ea', 'accent' => '#0b5cab', 'radius' => '6px', 'font' => '"Segoe UI", Roboto, sans-serif', 'hero' => 'linear-gradient(180deg,#e8f0fa,#f4f6f9)'],
        'glass' => ['bg' => '#1b1035', 'surface' => 'rgba(255,255,255,0.10)', 'text' => '#ffffff', 'muted' => '#c9c2e6', 'border' => 'rgba(255,255,255,0.18)', 'accent' => '#ff7ad9', 'radius' => '18px', 'font' => '"Inter", system-ui, sans-serif', 'hero' => 'linear-gradient(135deg,#3a1c71,#d76d77 55%,#ffaf7b)'],
    ];

    /** Screen types each platform has a dedicated layout for; others map to the nearest. */
    private const WEB_MAP = [
        'landing' => 'landing', 'home' => 'landing', 'onboarding' => 'landing', 'blog' => 'landing', 'other' => 'landing',
        'dashboard' => 'dashboard', 'settings' => 'dashboard', 'empty-state' => 'dashboard',
        'login' => 'login', 'signup' => 'login',
        'pricing' => 'pricing',
        'product-list' => 'catalog', 'search' => 'catalog', 'product-detail' => 'catalog', 'cart' => 'catalog', 'checkout' => 'catalog',
        'profile' => 'dashboard', 'chat' => 'dashboard',
    ];

    private const MOBILE_MAP = [
        'home' => 'home', 'landing' => 'home', 'product-list' => 'home', 'search' => 'home', 'other' => 'home', 'blog' => 'home',
        'login' => 'login', 'signup' => 'login', 'onboarding' => 'login',
        'product-detail' => 'detail', 'cart' => 'detail', 'checkout' => 'detail', 'pricing' => 'detail',
        'profile' => 'profile', 'settings' => 'profile', 'chat' => 'profile',
        'dashboard' => 'dashboard', 'empty-state' => 'dashboard',
    ];

    private const LABELS = [
        'ecommerce' => 'E-commerce', 'food' => 'Food delivery', 'health' => 'Healthcare', 'finance' => 'Fintech',
        'education' => 'E-learning', 'travel' => 'Travel', 'realestate' => 'Real estate', 'fitness' => 'Fitness',
        'agriculture' => 'Agritech', 'plumbing' => 'Plumbing', 'services' => 'Home services', 'social' => 'Community', 'productivity' => 'Productivity',
    ];

    private array $d;   // domain profile

    private array $t;   // theme

    private string $brand;

    /** The art direction this mockup is built in (typography, shapes, layout recipe). */
    private ?array $dir = null;

    public function render(string $prompt, string $platform, string $screenType, string $style, ?string $accent, ?string $categorySlug = null, ?array $direction = null): string
    {
        $this->d = DomainProfiles::forPrompt($prompt, $categorySlug);
        $this->t = self::STYLES[$style] ?? self::STYLES['modern'];
        $this->dir = $direction ?? DesignDirection::pick($prompt, $style, $accent, $this->d['key']);

        // The direction owns typography, corner radius and the accent colour;
        // the style picker still decides the light or dark base palette.
        $this->t['font'] = $this->dir['body'];
        $this->t['radius'] = $this->dir['radius'];
        $this->t['bg'] = $this->dir['tint'];

        if ($accent && preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
            $this->t['accent'] = $accent;
        } elseif (! empty($this->dir['accent'])) {
            $this->t['accent'] = $this->dir['accent'];
        }

        $this->t['hero'] = 'radial-gradient(125% 130% at 82% -25%, color-mix(in srgb, '.$this->t['accent'].' 16%, '.$this->dir['tint'].'), '.$this->dir['tint'].')';

        $this->brand = $this->d['brand'][mt_rand(0, count($this->d['brand']) - 1)];

        if ($platform === 'mobile') {
            $layout = self::MOBILE_MAP[$screenType] ?? 'home';
            $body = $this->{'mobile'.ucfirst($layout)}();
        } else {
            $layout = self::WEB_MAP[$screenType] ?? 'landing';
            $body = $this->{'web'.ucfirst($layout)}();
        }

        return $this->document($body, $platform === 'mobile');
    }

    /** @return array<string, mixed>|null */
    public function direction(): ?array
    {
        return $this->dir;
    }

    // ------------------------------------------------------------------ shell

    private function document(string $body, bool $mobile): string
    {
        $t = $this->t;
        $title = $this->e($this->brand);
        $maxW = $mobile ? '430px' : 'none';

        $dir = $this->dir ?? [];
        $google = empty($dir['google']) ? '' : '<link rel="preconnect" href="https://fonts.googleapis.com">'
            .'<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            .'<link href="https://fonts.googleapis.com/css2?family='.$dir['google'].'&display=swap" rel="stylesheet">';
        $display = $dir['display'] ?? $t['font'];
        $track = $dir['headSpacing'] ?? '-.02em';
        $weight = $dir['headWeight'] ?? 800;
        $hl = ($dir['language'] ?? '') === 'editorial' ? 'italic' : 'normal';
        $h1 = (int) round(45 * ($dir['headScale'] ?? 1));
        $pad = 78;
        $pattern = match ($dir['ornament'] ?? 'none') {
            'grid' => 'linear-gradient(color-mix(in srgb,var(--text) 4%,transparent) 1px,transparent 1px),linear-gradient(90deg,color-mix(in srgb,var(--text) 4%,transparent) 1px,transparent 1px)',
            'dots' => 'radial-gradient(color-mix(in srgb,var(--text) 7%,transparent) 1px,transparent 1px)',
            default => 'none',
        };
        $patternSize = match ($dir['ornament'] ?? 'none') {
            'grid' => '64px 64px',
            'dots' => '22px 22px',
            default => 'auto',
        };

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
{$google}
<style>
:root{--bg:{$t['bg']};--surface:{$t['surface']};--text:{$t['text']};--muted:{$t['muted']};--border:{$t['border']};--accent:{$t['accent']};--r:{$t['radius']};--hero:{$t['hero']};--display:{$display};--track:{$track};--h1:{$h1}px;--pad:{$pad}px}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:{$t['font']};background:var(--bg);color:var(--text);font-size:15px;line-height:1.5;-webkit-font-smoothing:antialiased;max-width:{$maxW};margin:0 auto;min-height:100vh;background-image:{$pattern};background-size:{$patternSize}}
a{color:inherit;text-decoration:none}
.wrap{max-width:1160px;margin:0 auto;padding:0 28px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 22px;border-radius:var(--r);background:var(--accent);color:#fff;font-weight:600;font-size:15px;border:0;cursor:pointer}
.btn.ghost{background:transparent;color:var(--text);border:1px solid var(--border)}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);backdrop-filter:blur(14px)}
.muted{color:var(--muted)}
.chip{padding:7px 14px;border-radius:999px;border:1px solid var(--border);font-size:13px;white-space:nowrap;background:var(--surface)}
.chip.on{background:var(--accent);border-color:var(--accent);color:#fff}
.ph{border-radius:calc(var(--r) - 4px);background:linear-gradient(135deg,color-mix(in srgb,var(--accent) 35%,transparent),color-mix(in srgb,var(--accent) 8%,transparent));display:flex;align-items:center;justify-content:center;font-size:30px}
.badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;background:color-mix(in srgb,var(--accent) 15%,transparent);color:var(--accent)}
.up{color:#16a34a}.down{color:#dc2626}
input{width:100%;padding:12px 14px;border-radius:var(--r);border:1px solid var(--border);background:var(--surface);color:var(--text);font:inherit}
h1,h2,h3{font-family:var(--display);line-height:1.12;letter-spacing:var(--track);font-weight:{$weight}}
.h2{font-size:34px;line-height:1.14}
.hl{font-style:{$hl};color:var(--accent)}
.kicker{display:inline-flex;align-items:center;gap:9px;font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--muted)}
.kicker::before{content:"";width:20px;height:1px;background:var(--accent);display:inline-block}
.sec{padding:var(--pad) 0}
.art{position:relative;overflow:hidden;background:linear-gradient(145deg,color-mix(in srgb,var(--accent) 16%,var(--surface)),color-mix(in srgb,var(--accent) 3%,var(--surface)));border:1px solid var(--border);border-radius:var(--r)}
.mini{width:38px;height:38px;border-radius:calc(var(--r)*.8);display:inline-flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--accent) 12%,var(--surface));font-size:17px}
.dot{width:8px;height:8px;border-radius:50%;background:var(--border);display:inline-block}
summary::-webkit-details-marker{display:none}
table{width:100%;border-collapse:collapse;font-size:14px}
th{text-align:left;font-weight:600;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.04em}
th,td{padding:12px 16px;border-bottom:1px solid var(--border)}
.grid{display:grid;gap:20px}
.logo{display:flex;align-items:center;gap:10px;font-weight:800;font-size:20px}
.logo i{width:30px;height:30px;border-radius:9px;background:var(--accent);display:inline-block}
</style>
</head>
<body>
{$body}
</body>
</html>
HTML;
    }

    private function webLanding(): string
    {
        $dir = $this->dir;

        $parts = [$this->nav(), $this->{'hero'.ucfirst($dir['hero'])}()];

        if ($dir['proof'] !== 'none') {
            $parts[] = $this->{'proof'.ucfirst($dir['proof'])}();
        }

        $features = $this->benefits();
        $showcase = $this->showcaseSection();

        foreach ($dir['showcaseFirst'] ? [$showcase, $features] : [$features, $showcase] as $section) {
            $parts[] = $section;
        }

        $parts[] = $this->extraSection();
        $parts[] = $this->{'cta'.ucfirst($dir['cta'])}();
        $parts[] = $this->{'footer'.ucfirst($dir['footer'])}();

        return implode("\n", array_filter($parts));
    }

    // ------------------------------------------------------------------- nav

    private function nav(): string
    {
        return match (mt_rand(0, 2)) {
            1 => $this->navPill(),
            2 => $this->navClassic(),
            default => $this->navSplit(),
        };
    }

    private function navClassic(): string
    {
        $links = $this->navLinks();

        return <<<HTML
<header style="border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--bg) 82%,transparent);backdrop-filter:blur(12px);position:sticky;top:0;z-index:20">
  <div class="wrap" style="display:flex;align-items:center;justify-content:space-between;height:70px">
    <div class="logo"><i></i>{$this->e($this->brand)}</div>
    <nav style="display:flex;gap:26px;font-size:14.5px">{$links}</nav>
    <div style="display:flex;gap:10px"><a class="btn ghost" href="#">Log in</a><a class="btn" href="#">{$this->e($this->d['cta'])}</a></div>
  </div>
</header>
HTML;
    }

    private function navPill(): string
    {
        $links = $this->navLinks();

        return <<<HTML
<header style="padding:16px 0">
  <div class="wrap" style="display:flex;align-items:center;gap:18px">
    <div class="logo"><i></i>{$this->e($this->brand)}</div>
    <nav style="display:flex;gap:4px;font-size:14px;background:var(--surface);border:1px solid var(--border);border-radius:999px;padding:5px 8px;margin:0 auto">{$links}</nav>
    <a class="btn" href="#">{$this->e($this->d['cta'])}</a>
  </div>
</header>
HTML;
    }

    private function navSplit(): string
    {
        $links = $this->navLinks();

        return <<<HTML
<header style="border-bottom:1px solid var(--border)">
  <div class="wrap" style="display:grid;grid-template-columns:1fr auto;gap:20px;align-items:center;padding:18px 28px 16px">
    <div>
      <div class="logo" style="font-size:22px"><i></i>{$this->e($this->brand)}</div>
      <div class="muted" style="font-size:12.5px;margin-top:2px">{$this->e($this->d['tagline'])}</div>
    </div>
    <div style="display:flex;align-items:center;gap:22px">
      <nav style="display:flex;gap:20px;font-size:13.5px">{$links}</nav>
      <a class="btn" href="#">{$this->e($this->d['cta'])}</a>
    </div>
  </div>
</header>
HTML;
    }

    private function navLinks(): string
    {
        return implode('', array_map(
            fn ($l, $i) => '<a href="#" style="padding:6px 10px;'.($i === 0 && mt_rand(0, 1) ? 'font-weight:600' : 'color:var(--muted)').'">'.$this->e($l).'</a>',
            $this->d['nav'],
            array_keys($this->d['nav']),
        ));
    }

    // ------------------------------------------------------------------ heroes

    private function heroSplit(): string
    {
        [$kicker, $headline, $sub, $primary, $secondary] = $this->heroCopy();

        return <<<HTML
<section style="background:var(--hero)">
  <div class="wrap" style="display:grid;grid-template-columns:1.02fr .98fr;gap:54px;align-items:center;padding:var(--pad) 28px">
    <div>
      <span class="kicker">{$kicker}</span>
      <h1 style="font-size:var(--h1);margin:18px 0 16px">{$headline}</h1>
      <p class="muted" style="font-size:17.5px;max-width:500px;margin-bottom:28px">{$sub}</p>
      <div style="display:flex;gap:12px;flex-wrap:wrap"><a class="btn" href="#">{$primary} →</a><a class="btn ghost" href="#">{$secondary}</a></div>
      {$this->heroTrust()}
    </div>
    {$this->visual()}
  </div>
</section>
HTML;
    }

    private function heroCentered(): string
    {
        [$kicker, $headline, $sub, $primary, $secondary] = $this->heroCopy();

        return <<<HTML
<section style="background:var(--hero);text-align:center">
  <div class="wrap" style="padding:var(--pad) 28px calc(var(--pad) - 20px)">
    <span class="kicker" style="justify-content:center">{$kicker}</span>
    <h1 style="font-size:var(--h1);margin:20px auto 16px;max-width:830px">{$headline}</h1>
    <p class="muted" style="font-size:17.5px;max-width:560px;margin:0 auto 28px">{$sub}</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap"><a class="btn" href="#">{$primary}</a><a class="btn ghost" href="#">{$secondary}</a></div>
    {$this->heroTrust('center')}
  </div>
  <div class="wrap" style="padding:0 28px var(--pad)">{$this->visual(true)}</div>
</section>
HTML;
    }

    private function heroEditorial(): string
    {
        [$kicker, $headline, $sub, $primary, $secondary] = $this->heroCopy();
        $chips = '';
        foreach (array_slice($this->d['chips'], 0, 5) as $chip) {
            $chips .= '<span style="padding:0 18px;border-left:1px solid var(--border);font-size:13px;color:var(--muted)">'.$this->e($chip).'</span>';
        }

        return <<<HTML
<section style="background:var(--hero)">
  <div class="wrap" style="padding:var(--pad) 28px 0">
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:20px;border-bottom:1px solid var(--border);padding-bottom:16px">
      <span class="kicker">{$kicker}</span>
      <span class="muted" style="font-size:12.5px">{$this->e($this->brand)} — est. 2019</span>
    </div>
    <div style="display:grid;grid-template-columns:1.35fr .65fr;gap:40px;align-items:end;padding:34px 0 30px">
      <h1 style="font-size:calc(var(--h1) * 1.24);line-height:.98">{$headline}</h1>
      <div>
        <p class="muted" style="font-size:16.5px;margin-bottom:22px">{$sub}</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn" href="#">{$primary} →</a><a class="btn ghost" href="#">{$secondary}</a></div>
      </div>
    </div>
  </div>
  <div class="wrap" style="padding:0 28px">{$this->visual(true)}</div>
  <div style="border-top:1px solid var(--border);border-bottom:1px solid var(--border);margin-top:34px">
    <div class="wrap" style="display:flex;height:48px;align-items:center;overflow:hidden">{$chips}</div>
  </div>
</section>
HTML;
    }

    private function heroBento(): string
    {
        [$kicker, $headline, $sub, $primary, $secondary] = $this->heroCopy();
        $tiles = '';
        foreach (array_slice($this->d['features'], 0, 3) as $i => [$icon, $title]) {
            $tiles .= "<div class=\"card\" style=\"padding:20px;display:flex;flex-direction:column;justify-content:space-between;min-height:130px;background:".($i === 0 ? 'var(--accent);color:#fff;border-color:transparent' : 'var(--surface)')."\"><span style=\"font-size:22px\">{$icon}</span><strong style=\"font-size:14.5px;line-height:1.3\">{$this->e($title)}</strong></div>";
        }
        $stat = $this->d['heroStats'][0] ?? ['24/7', 'Support'];

        return <<<HTML
<section style="background:var(--hero)">
  <div class="wrap" style="padding:var(--pad) 28px">
    <div style="display:grid;grid-template-columns:1.4fr .6fr;gap:22px;margin-bottom:22px">
      <div class="card" style="padding:38px 34px;background:var(--surface)">
        <span class="kicker">{$kicker}</span>
        <h1 style="font-size:var(--h1);margin:16px 0 14px;max-width:560px">{$headline}</h1>
        <p class="muted" style="font-size:16.5px;max-width:480px;margin-bottom:24px">{$sub}</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn" href="#">{$primary} →</a><a class="btn ghost" href="#">{$secondary}</a></div>
      </div>
      <div style="display:grid;grid-template-rows:1fr auto;gap:22px">
        <div class="card" style="padding:24px;display:flex;flex-direction:column;justify-content:center">
          <div style="font-size:38px;font-weight:800;letter-spacing:-.03em">{$stat[0]}</div>
          <div class="muted" style="font-size:13.5px">{$stat[1]}</div>
        </div>
        <div class="card" style="padding:20px;background:var(--surface)">
          <div class="muted" style="font-size:11.5px;letter-spacing:.12em;text-transform:uppercase;margin-bottom:10px">{$this->e($this->d['shelf'] ?? 'Popular')}</div>
          <div style="font-weight:600;font-size:14px">{$this->e($this->d['items'][0][0])}</div>
          <div class="muted" style="font-size:13px">{$this->e($this->d['items'][0][1])}</div>
        </div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:22px">{$tiles}</div>
  </div>
</section>
HTML;
    }

    private function heroStat(): string
    {
        [$kicker, $headline, $sub, $primary, $secondary] = $this->heroCopy();
        $stats = '';
        $heroStats = $this->d['heroStats'] ?? [['50k+', 'Happy users'], ['4.9★', 'Average rating'], ['24/7', 'Support']];
        foreach ($heroStats as [$value, $label]) {
            $stats .= '<div style="padding:18px 0;border-top:1px solid var(--border)"><div style="font-size:30px;font-weight:700;letter-spacing:-.02em">'.$this->e($this->jitter($value)).'</div><div class="muted" style="font-size:13px">'.$this->e($label).'</div></div>';
        }

        return <<<HTML
<section style="background:var(--hero)">
  <div class="wrap" style="display:grid;grid-template-columns:1.3fr .7fr;gap:56px;padding:var(--pad) 28px;align-items:center">
    <div>
      <span class="kicker">{$kicker}</span>
      <h1 style="font-size:var(--h1);margin:18px 0 16px;max-width:620px">{$headline}</h1>
      <p class="muted" style="font-size:17px;max-width:520px;margin-bottom:26px">{$sub}</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn" href="#">{$primary}</a><a class="btn ghost" href="#">{$secondary}</a></div>
    </div>
    <div>{$stats}</div>
  </div>
</section>
HTML;
    }

    /** @return array{0: string, 1: string, 2: string, 3: string, 4: string} */
    private function heroCopy(): array
    {
        $kicker = strtoupper(self::LABELS[$this->d['key']] ?? 'New').' · '.strtoupper($this->d['nav'][0]);
        $secondary = ['See how it works', 'View pricing', 'Talk to us', 'Browse all'][mt_rand(0, 3)];

        return [$kicker, $this->highlight($this->d['tagline']), $this->e($this->d['sub']), $this->e($this->d['cta']), $secondary];
    }

    private function heroTrust(string $align = 'left'): string
    {
        $items = ['Rated 4.9/5', 'Trusted locally', 'Transparent pricing', 'Fast replies'];
        shuffle($items);
        $html = '';
        foreach (array_slice($items, 0, 3) as $item) {
            $html .= '<span style="display:inline-flex;align-items:center;gap:7px;font-size:12.5px;color:var(--muted)"><span style="color:var(--accent)">✓</span>'.$this->e($item).'</span>';
        }

        return '<div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:30px;'.($align === 'center' ? 'justify-content:center' : '').'">'.$html.'</div>';
    }

    // ---------------------------------------------------------------- visuals

    /** Abstract product imagery built from the brief's own data. */
    private function visual(bool $wide = false): string
    {
        return match ($this->dir['visual']) {
            'tiles' => $this->visualTiles($wide),
            'chart' => $this->visualChart($wide),
            'phone' => $this->visualPhone($wide),
            default => $this->visualPanel($wide),
        };
    }

    private function visualPanel(bool $wide = false): string
    {
        $rows = '';
        foreach ($this->pickItems(3) as $i => [$name, $meta]) {
            $rows .= "<div style=\"display:flex;align-items:center;gap:12px;padding:10px 0;border-top:1px solid var(--border)\"><span class=\"mini\">".$this->d['features'][$i % 3][0]."</span><div style=\"flex:1;min-width:0\"><div style=\"font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis\">{$this->e($name)}</div><div class=\"muted\" style=\"font-size:11.5px\">{$this->e($meta)}</div></div><span class=\"badge\">".($i % 2 ? 'Active' : 'New')."</span></div>";
        }

        return <<<HTML
<div class="card" style="padding:18px;box-shadow:0 24px 60px -32px rgba(0,0,0,.4)">
  <div style="display:flex;gap:6px;margin-bottom:14px"><span class="dot"></span><span class="dot"></span><span class="dot"></span><span class="muted" style="font-size:11px;margin-left:auto">{$this->e($this->brand)}</span></div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px">
    <div style="padding:12px;border-radius:calc(var(--r)*.8);background:var(--hero)"><div class="muted" style="font-size:10.5px;text-transform:uppercase;letter-spacing:.08em">{$this->e($this->d['stats'][0][0])}</div><div style="font-weight:700;font-size:17px">{$this->e($this->jitter($this->d['stats'][0][1]))}</div></div>
    <div style="padding:12px;border-radius:calc(var(--r)*.8);background:var(--hero)"><div class="muted" style="font-size:10.5px;text-transform:uppercase;letter-spacing:.08em">{$this->e($this->d['stats'][1][0])}</div><div style="font-weight:700;font-size:17px">{$this->e($this->jitter($this->d['stats'][1][1]))}</div></div>
    <div style="padding:12px;border-radius:calc(var(--r)*.8);background:var(--accent);color:#fff"><div style="font-size:10.5px;text-transform:uppercase;letter-spacing:.08em;opacity:.8">{$this->e($this->d['stats'][2][0])}</div><div style="font-weight:700;font-size:17px">{$this->e($this->jitter($this->d['stats'][2][1]))}</div></div>
  </div>
  <div>{$rows}</div>
</div>
HTML;
    }

    private function visualTiles(bool $wide = false): string
    {
        $tiles = '';
        foreach (array_slice($this->d['items'], 0, 4) as $i => $item) {
            $tiles .= '<div style="padding:16px;border-radius:calc(var(--r)*.9);background:'.($i % 3 === 0 ? 'var(--accent);color:#fff' : 'var(--hero)').';border:1px solid var(--border)"><div style="font-size:18px;margin-bottom:26px">'.$this->d['features'][$i % 3][0].'</div><div style="font-size:12.5px;font-weight:600;line-height:1.3">'.$this->e($item[0]).'</div><div style="font-size:11.5px;opacity:.75;margin-top:3px">'.$this->e($item[1]).'</div></div>';
        }

        return '<div style="display:grid;grid-template-columns:repeat('.($wide ? 4 : 2).',1fr);gap:12px">'.$tiles.'</div>';
    }

    private function visualChart(bool $wide = false): string
    {
        $bars = '';
        $values = [46, 62, 55, 74, 68, 88, 79, 94, 72, 90, 84, 97];
        shuffle($values);
        foreach ($values as $i => $h) {
            $bars .= "<div style=\"flex:1;height:{$h}%;background:var(--accent);opacity:".($i === 7 ? '1' : '.28').";border-radius:3px 3px 0 0\"></div>";
        }

        return <<<HTML
<div class="card" style="padding:20px">
  <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:6px">
    <div><div class="muted" style="font-size:11px;letter-spacing:.1em;text-transform:uppercase">{$this->e($this->d['stats'][0][0])}</div><div style="font-size:26px;font-weight:800;letter-spacing:-.02em">{$this->e($this->jitter($this->d['stats'][0][1]))}</div></div>
    <span class="badge">{$this->e($this->d['stats'][0][2])}</span>
  </div>
  <div style="display:flex;align-items:flex-end;gap:6px;height:150px;margin-top:16px">{$bars}</div>
  <div class="muted" style="font-size:11px;margin-top:10px">Last 12 months</div>
</div>
HTML;
    }

    private function visualPhone(bool $wide = false): string
    {
        $rows = '';
        foreach ($this->pickItems(3) as $i => [$name, $meta]) {
            $rows .= "<div style=\"display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)\"><span class=\"mini\">".$this->d['features'][$i % 3][0]."</span><div style=\"flex:1;font-size:12px;font-weight:600\">{$this->e($name)}</div><span style=\"font-size:11.5px;color:var(--accent);font-weight:700\">{$this->e($meta)}</span></div>";
        }

        return <<<HTML
<div style="display:flex;justify-content:center">
  <div class="card" style="width:270px;padding:14px;border-radius:28px;box-shadow:0 30px 70px -40px rgba(0,0,0,.55)">
    <div style="height:5px;width:60px;border-radius:9px;background:var(--border);margin:4px auto 14px"></div>
    <div style="padding:16px;border-radius:calc(var(--r)*.9);background:var(--accent);color:#fff;margin-bottom:14px">
      <div style="font-size:10.5px;letter-spacing:.1em;text-transform:uppercase;opacity:.8">{$this->e($this->d['nav'][0])}</div>
      <div style="font-weight:700;font-size:15px;line-height:1.25;margin-top:4px">{$this->e($this->d['cta'])}</div>
    </div>
    {$rows}
  </div>
</div>
HTML;
    }

    // --------------------------------------------------------------- benefits

    private function benefits(): string
    {
        return match ($this->dir['features']) {
            'bento' => $this->benefitsBento(),
            'rows' => $this->benefitsRows(),
            'split-list' => $this->benefitsSplitList(),
            default => $this->benefitsCards(),
        };
    }

    private function benefitsHeading(): string
    {
        return '<h2 class="h2">'.str_replace(['Why people choose', 'Why'], ['Why', 'Why'], $this->highlight('Why people choose '.$this->brand)).'</h2><p class="muted" style="max-width:520px">'.($this->dir['features'] === 'rows' ? 'What you get, step by step.' : 'Built around what actually matters to you.').'</p>';
    }

    private function benefitsCards(): string
    {
        $cards = '';
        foreach ($this->d['features'] as [$icon, $title, $text]) {
            $cards .= "<div class=\"card\" style=\"padding:26px\"><div class=\"mini\" style=\"width:46px;height:46px;font-size:22px;margin-bottom:16px\">{$icon}</div><h3 style=\"font-size:18px;margin-bottom:6px\">{$this->e($title)}</h3><p class=\"muted\" style=\"font-size:14px\">{$this->e($text)}</p></div>";
        }

        return <<<HTML
<section class="sec"><div class="wrap">{$this->benefitsHeading()}
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:36px">{$cards}</div>
</div></section>
HTML;
    }

    private function benefitsBento(): string
    {
        [$icon, $title, $text] = $this->d['features'][0];
        $small = '';
        foreach (array_slice($this->d['features'], 1) as [$i2, $t2, $x2]) {
            $small .= "<div class=\"card\" style=\"padding:22px\"><div class=\"mini\" style=\"margin-bottom:12px\">{$i2}</div><h3 style=\"font-size:16px;margin-bottom:5px\">{$this->e($t2)}</h3><p class=\"muted\" style=\"font-size:13.5px\">{$this->e($x2)}</p></div>";
        }
        $stat = $this->d['heroStats'][0] ?? ['24/7', 'Support'];

        return <<<HTML
<section class="sec"><div class="wrap">{$this->benefitsHeading()}
  <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:20px;margin-top:36px">
    <div class="card" style="padding:32px;background:var(--accent);color:#fff;border-color:transparent;display:flex;flex-direction:column;justify-content:space-between;min-height:280px">
      <div class="mini" style="width:52px;height:52px;font-size:24px;background:rgba(255,255,255,.18);color:#fff">{$icon}</div>
      <div><h3 style="font-size:26px;margin-bottom:8px;line-height:1.15">{$this->e($title)}</h3><p style="opacity:.86;font-size:14.5px;max-width:420px">{$this->e($text)}</p></div>
    </div>
    <div style="display:grid;gap:20px">{$small}</div>
  </div>
  <div class="card" style="margin-top:20px;padding:20px 26px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div style="display:flex;gap:28px;flex-wrap:wrap">{$this->proofStatsInline()}</div>
    <a class="btn ghost" href="#">{$this->e($this->d['nav'][0])} →</a>
  </div>
</div></section>
HTML;
    }

    private function benefitsRows(): string
    {
        $rows = '';
        foreach ($this->d['features'] as $i => [$icon, $title, $text]) {
            $rows .= "<div style=\"display:grid;grid-template-columns:56px 1fr auto;gap:20px;align-items:center;padding:24px 0;border-top:1px solid var(--border)\"><span class=\"muted\" style=\"font-size:13px\">0".($i + 1)."</span><div><h3 style=\"font-size:20px;margin-bottom:4px\">{$this->e($title)}</h3><p class=\"muted\" style=\"font-size:14px\">{$this->e($text)}</p></div><span style=\"font-size:22px\">{$icon}</span></div>";
        }

        return <<<HTML
<section class="sec"><div class="wrap" style="display:grid;grid-template-columns:.85fr 1.15fr;gap:56px">
  <div>{$this->benefitsHeading()}<a class="btn" href="#" style="margin-top:26px">{$this->e($this->d['cta'])}</a></div>
  <div>{$rows}</div>
</div></section>
HTML;
    }

    private function benefitsSplitList(): string
    {
        $items = '';
        foreach ($this->d['features'] as [$icon, $title, $text]) {
            $items .= "<div style=\"display:flex;gap:16px;padding:18px 0;border-bottom:1px solid var(--border)\"><div class=\"mini\" style=\"flex:none\">{$icon}</div><div><div style=\"font-weight:600;font-size:15.5px\">{$this->e($title)}</div><div class=\"muted\" style=\"font-size:13.5px\">{$this->e($text)}</div></div></div>";
        }

        return <<<HTML
<section class="sec"><div class="wrap" style="display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:start">
  <div style="position:sticky;top:100px">{$this->benefitsHeading()}
    <div class="card" style="margin-top:28px;padding:20px;display:flex;align-items:center;gap:14px">
      <div class="mini" style="background:var(--accent);color:#fff">★</div>
      <div><div style="font-weight:600;font-size:13.5px">{$this->e($this->d['stats'][3][0])}: {$this->e($this->jitter($this->d['stats'][3][1]))}</div><div class="muted" style="font-size:12.5px">According to last month's customers.</div></div>
    </div>
  </div>
  <div>{$items}</div>
</div></section>
HTML;
    }

    // ------------------------------------------------------------ proof/extra

    private function proofStats(): string
    {
        return <<<HTML
<section style="border-top:1px solid var(--border);border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--surface) 60%,var(--bg))">
  <div class="wrap" style="display:grid;grid-template-columns:repeat(4,1fr);gap:18px;padding:30px 28px">{$this->proofStatsInline()}</div>
</section>
HTML;
    }

    private function proofStatsInline(): string
    {
        $html = '';
        foreach (array_slice($this->d['stats'], 0, 4) as [$label, $value, $delta]) {
            $html .= "<div><div style=\"font-size:26px;font-weight:700;letter-spacing:-.02em\">".$this->e($this->jitter($value))."</div><div class=\"muted\" style=\"font-size:12.5px\">".$this->e($label)."</div></div>";
        }

        return $html;
    }

    private function proofLogos(): string
    {
        $names = ['Northside', 'Verdant', 'Ashby & Co', 'Lumen', 'Harbour', 'Pinewood'];
        shuffle($names);
        $marks = '';
        foreach (array_slice($names, 0, 5) as $name) {
            $marks .= '<span style="font-family:var(--display);font-size:19px;font-weight:600;opacity:.45">'.$this->e($name).'</span>';
        }

        return <<<HTML
<section style="border-bottom:1px solid var(--border)">
  <div class="wrap" style="padding:26px 28px;display:flex;align-items:center;justify-content:space-between;gap:28px;flex-wrap:wrap">
    <span class="muted" style="font-size:11.5px;letter-spacing:.14em;text-transform:uppercase">Trusted by teams like</span>
    <div style="display:flex;gap:34px;flex-wrap:wrap">{$marks}</div>
  </div>
</section>
HTML;
    }

    private function proofQuote(): string
    {
        [$name, $meta] = $this->d['items'][0];

        return <<<HTML
<section style="border-bottom:1px solid var(--border)">
  <div class="wrap" style="padding:60px 28px;display:grid;grid-template-columns:1fr 1fr;gap:50px;align-items:center">
    <blockquote style="font-family:var(--display);font-size:28px;line-height:1.25;letter-spacing:var(--track)">
      “We stopped chasing problems. {$this->e($this->brand)} took care of it and the follow-up was even better.”
    </blockquote>
    <div style="display:flex;align-items:center;gap:16px">
      <div style="width:52px;height:52px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700">A</div>
      <div><div style="font-weight:600">Alex Rahman</div><div class="muted" style="font-size:13px">Customer since 2021 · {$this->e($meta)}</div></div>
    </div>
  </div>
</section>
HTML;
    }

    private function extraSection(): string
    {
        return match ($this->dir['extra']) {
            'faq' => $this->extraFaq(),
            'process' => $this->extraProcess(),
            default => $this->extraTestimonial(),
        };
    }

    private function extraTestimonial(): string
    {
        $cards = '';
        $quotes = [
            'Booked in two minutes and sorted the same afternoon. No surprises on the bill.',
            'Everything was explained before starting, and nothing was left half done.',
            'Third time using them now — consistent every single time.',
        ];
        shuffle($quotes);
        foreach (array_slice($this->d['items'], 1, 3) as $i => $item) {
            $cards .= "<div class=\"card\" style=\"padding:24px\"><div style=\"color:var(--accent);font-size:13px;letter-spacing:2px;margin-bottom:10px\">★★★★★</div><p style=\"font-size:14.5px;line-height:1.55;margin-bottom:16px\">".$this->e($quotes[$i])."</p><div class=\"muted\" style=\"font-size:12.5px\">".$this->e(['Nadia I.', 'Marcus L.', 'Sara K.'][$i]).' · '.$this->e($item[0]).'</div></div>';
        }

        return <<<HTML
<section class="sec"><div class="wrap">
  <div style="display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:30px">
    <div><span class="kicker">Reviews</span><h2 class="h2" style="margin-top:14px">People who stopped worrying</h2></div>
    <span class="muted" style="font-size:13px">{$this->e($this->jitter('1,840'))} reviews · average 4.9</span>
  </div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px">{$cards}</div>
</div></section>
HTML;
    }

    private function extraFaq(): string
    {
        $questions = [
            'How fast can someone come out?',
            'Do you give a price before starting?',
            'What areas do you cover?',
            'Is the work guaranteed?',
        ];
        $answer = $this->d['sub'];
        $rows = '';
        foreach ($questions as $i => $q) {
            $rows .= "<details style=\"border-bottom:1px solid var(--border);padding:18px 0\"".($i === 0 ? ' open' : '')."><summary style=\"cursor:pointer;font-weight:600;font-size:15.5px;list-style:none;display:flex;justify-content:space-between;gap:16px\">{$this->e($q)}<span style=\"color:var(--accent)\">+</span></summary><p class=\"muted\" style=\"font-size:14px;margin-top:10px;max-width:620px\">{$this->e($answer)}</p></details>";
        }

        return <<<HTML
<section class="sec"><div class="wrap" style="display:grid;grid-template-columns:.8fr 1.2fr;gap:56px">
  <div><span class="kicker">Questions</span><h2 class="h2" style="margin-top:14px">Everything you might ask</h2><p class="muted" style="font-size:14px;margin-top:10px">Still unsure? Call {$this->e($this->brand)} and talk to a real person.</p></div>
  <div>{$rows}</div>
</div></section>
HTML;
    }

    private function extraProcess(): string
    {
        $steps = [
            ['Tell us what you need', 'A short form or message. Details help but are not required.'],
            ['Agree the plan and price', 'Everything is confirmed in writing before any work starts.'],
            ['Done and checked', 'We finish, tidy up and leave the guarantee with you.'],
        ];
        $html = '';
        foreach ($steps as $i => [$title, $text]) {
            $html .= "<div style=\"display:grid;grid-template-columns:44px 1fr;gap:16px;padding:22px 0;border-top:1px solid var(--border)\"><div style=\"width:32px;height:32px;border-radius:50%;border:1px solid var(--accent);color:var(--accent);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700\">".($i + 1)."</div><div><h3 style=\"font-size:17px;margin-bottom:4px\">{$this->e($title)}</h3><p class=\"muted\" style=\"font-size:13.5px\">{$this->e($text)}</p></div></div>";
        }

        return <<<HTML
<section class="sec"><div class="wrap" style="display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:start">
  <div><span class="kicker">How it works</span><h2 class="h2" style="margin-top:14px">Three steps, no surprises</h2><p class="muted" style="font-size:14px;margin-top:10px">{$this->e($this->d['sub'])}</p><a class="btn" href="#" style="margin-top:24px">{$this->e($this->d['cta'])}</a></div>
  <div>{$html}</div>
</div></section>
HTML;
    }

    // --------------------------------------------------------------- showcase

    private function showcaseSection(): string
    {
        return match ($this->dir['showcase']) {
            'rows' => $this->showcaseRows(),
            'pricelist' => $this->showcasePriceList(),
            'bento' => $this->showcaseBento(),
            default => $this->showcaseGrid(),
        };
    }

    private function showcaseHead(string $title, string $hint): string
    {
        return <<<HTML
<div style="display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:28px;flex-wrap:wrap">
  <div><span class="kicker">{$this->e($this->d['shelf'] ?? 'Popular right now')}</span><h2 class="h2" style="margin-top:14px">{$this->e($title)}</h2></div>
  <span class="muted" style="font-size:13px">{$this->e($hint)}</span>
</div>
HTML;
    }

    private function showcaseGrid(): string
    {
        $cards = '';
        foreach ($this->pickItems(3) as $i => [$name, $meta]) {
            $cards .= "<div class=\"card\" style=\"padding:12px\"><div class=\"art\" style=\"height:150px;margin-bottom:12px\"><div style=\"position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:36px\">".$this->d['features'][$i % 3][0]."</div><span style=\"position:absolute;top:10px;left:10px;font-size:10.5px;letter-spacing:.1em;text-transform:uppercase;background:var(--surface);color:var(--text);padding:3px 8px;border-radius:999px\">".$this->e($this->d['chips'][$i + 1] ?? 'Popular')."</span></div><div style=\"display:flex;justify-content:space-between;align-items:baseline;gap:8px\"><strong style=\"font-size:14.5px;line-height:1.3\">{$this->e($name)}</strong><span style=\"font-weight:700;color:var(--accent);white-space:nowrap;font-size:14px\">{$this->e($meta)}</span></div><div class=\"muted\" style=\"font-size:12.5px;margin-top:4px\">Clear pricing · fast replies</div></div>";
        }

        return <<<HTML
<section class="sec" style="padding-top:calc(var(--pad) * .55)"><div class="wrap">
  {$this->showcaseHead('What we do most', 'See all →')}
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px">{$cards}</div>
</div></section>
HTML;
    }

    private function showcaseRows(): string
    {
        $rows = '';
        foreach ($this->pickItems(4) as $i => [$name, $meta]) {
            $rows .= "<div style=\"display:grid;grid-template-columns:64px 1fr auto auto;gap:18px;align-items:center;padding:16px 0;border-top:1px solid var(--border)\"><div class=\"art\" style=\"height:52px;width:64px;display:flex;align-items:center;justify-content:center;font-size:20px\">".$this->d['features'][$i % 3][0]."</div><div><div style=\"font-weight:600;font-size:15px\">{$this->e($name)}</div><div class=\"muted\" style=\"font-size:12.5px\">".$this->e($this->d['chips'][min($i + 1, count($this->d['chips']) - 1)])." · available this week</div></div><span class=\"badge\">{$this->e($meta)}</span><a href=\"#\" style=\"font-size:13px;font-weight:600;color:var(--accent)\">Choose →</a></div>";
        }

        return <<<HTML
<section class="sec" style="padding-top:calc(var(--pad) * .55)"><div class="wrap">
  {$this->showcaseHead('Services and prices', 'Updated this week')}
  <div>{$rows}</div>
</div></section>
HTML;
    }

    private function showcasePriceList(): string
    {
        $left = '';
        $right = '';
        foreach ($this->pickItems(6) as $i => [$name, $meta]) {
            $row = "<div style=\"display:flex;align-items:baseline;gap:8px;padding:12px 0\"><span style=\"font-size:14.5px\">{$this->e($name)}</span><span style=\"flex:1;border-bottom:1px dotted var(--border)\"></span><span style=\"font-weight:700;font-size:14px;white-space:nowrap\">{$this->e($meta)}</span></div>";
            $i % 2 === 0 ? $left .= $row : $right .= $row;
        }

        return <<<HTML
<section class="sec" style="padding-top:calc(var(--pad) * .55)"><div class="wrap">
  {$this->showcaseHead('Clear pricing, in writing', 'No call-out fee')}
  <div class="card" style="padding:14px 30px;display:grid;grid-template-columns:1fr 1fr;gap:0 46px">{$left}<div style="border-left:1px solid var(--border);margin:8px 0;grid-column:2;grid-row:1/-1;display:none"></div>{$right}</div>
  <div class="muted" style="font-size:12.5px;margin-top:12px">Prices include parts and labour. Anything extra is agreed with you first.</div>
</div></section>
HTML;
    }

    private function showcaseBento(): string
    {
        [$first] = $this->pickItems(1);
        $rest = '';
        foreach ($this->pickItems(3, 1) as $i => [$name, $meta]) {
            $rest .= "<div class=\"card\" style=\"padding:18px;display:flex;flex-direction:column;justify-content:space-between;min-height:120px\"><span style=\"font-size:20px\">".$this->d['features'][$i % 3][0]."</span><div><div style=\"font-weight:600;font-size:14px\">{$this->e($name)}</div><div class=\"muted\" style=\"font-size:12.5px\">{$this->e($meta)}</div></div></div>";
        }

        return <<<HTML
<section class="sec" style="padding-top:calc(var(--pad) * .55)"><div class="wrap">
  {$this->showcaseHead('A look at the work', 'Real jobs, real prices')}
  <div style="display:grid;grid-template-columns:1.3fr 1fr;gap:20px">
    <div class="card art" style="min-height:300px;padding:26px;display:flex;flex-direction:column;justify-content:flex-end">
      <span style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted)">{$this->e($this->d['nav'][0])}</span>
      <div style="font-size:26px;font-weight:700;letter-spacing:-.02em;margin-top:8px;max-width:320px">{$this->e($first[0])}</div>
      <div style="margin-top:14px"><a class="btn" href="#">{$this->e($this->d['cta'])}</a></div>
    </div>
    <div style="display:grid;gap:20px">{$rest}</div>
  </div>
</div></section>
HTML;
    }

    // ------------------------------------------------------------------- cta

    private function ctaBand(): string
    {
        return <<<HTML
<section class="wrap" style="padding:0 28px var(--pad)">
  <div style="border-radius:calc(var(--r) * 1.4);background:var(--accent);color:#fff;padding:46px;display:flex;justify-content:space-between;align-items:center;gap:26px;flex-wrap:wrap">
    <div><h2 style="font-family:var(--display);font-size:32px;letter-spacing:var(--track);line-height:1.1">Ready when you are</h2><p style="opacity:.85;margin-top:8px;font-size:15px">Talk to {$this->e($this->brand)} — clear pricing and no hidden extras.</p></div>
    <div style="display:flex;gap:10px"><a class="btn" style="background:#fff;color:var(--accent)" href="#">{$this->e($this->d['cta'])}</a><a class="btn ghost" style="color:#fff;border-color:rgba(255,255,255,.45)" href="#">Talk to us</a></div>
  </div>
</section>
HTML;
    }

    private function ctaSplit(): string
    {
        return <<<HTML
<section class="wrap" style="padding:0 28px var(--pad)">
  <div class="card" style="padding:44px;display:grid;grid-template-columns:1.2fr .8fr;gap:34px;align-items:center;background:var(--hero)">
    <div><span class="kicker">Get started</span><h2 style="font-family:var(--display);font-size:34px;letter-spacing:var(--track);line-height:1.1;margin-top:12px">{$this->e($this->d['tagline'])}</h2><p class="muted" style="font-size:14.5px;margin-top:10px;max-width:460px">{$this->e($this->d['sub'])}</p></div>
    <div style="display:flex;flex-direction:column;gap:10px"><a class="btn" href="#">{$this->e($this->d['cta'])}</a><a class="btn ghost" href="#">Ask a question</a><span class="muted" style="font-size:12px;text-align:center">Replies within 15 minutes</span></div>
  </div>
</section>
HTML;
    }

    private function ctaTicket(): string
    {
        return <<<HTML
<section class="wrap" style="padding:0 28px var(--pad)">
  <div style="max-width:640px;margin:0 auto;text-align:center;border:1px dashed var(--border);border-radius:calc(var(--r) * 1.2);padding:42px;background:color-mix(in srgb,var(--surface) 70%,transparent)">
    <span class="kicker" style="justify-content:center">Same-day slots</span>
    <h2 style="font-family:var(--display);font-size:30px;letter-spacing:var(--track);line-height:1.15;margin:14px 0 8px">Book a visit in under a minute</h2>
    <p class="muted" style="font-size:14px;margin-bottom:22px">Pick a time, we confirm by text. Pay only when the job is done.</p>
    <a class="btn" href="#">{$this->e($this->d['cta'])}</a>
    <div class="muted" style="font-size:11.5px;margin-top:14px;letter-spacing:.08em;text-transform:uppercase">{$this->e($this->jitter('1,840'))} jobs completed this year</div>
  </div>
</section>
HTML;
    }

    // ---------------------------------------------------------------- footer

    private function footerColumns(): string
    {
        $cols = '';
        foreach ([[$this->d['nav'][0], $this->d['nav']], ['Company', ['About', 'Reviews', 'Careers', 'Contact']], ['Support', ['Help centre', 'FAQ', 'Guarantee', 'Areas']]] as [$title, $links]) {
            $list = '';
            foreach ($links as $link) {
                $list .= '<a href="#" class="muted" style="display:block;font-size:13px;padding:4px 0">'.$this->e($link).'</a>';
            }
            $cols .= '<div><div style="font-size:11.5px;letter-spacing:.12em;text-transform:uppercase;margin-bottom:10px">'.$this->e($title).'</div>'.$list.'</div>';
        }

        return <<<HTML
<footer style="border-top:1px solid var(--border);background:color-mix(in srgb,var(--surface) 55%,var(--bg))">
  <div class="wrap" style="padding:46px 28px 26px;display:grid;grid-template-columns:1.4fr repeat(3,1fr);gap:34px">
    <div><div class="logo"><i></i>{$this->e($this->brand)}</div><p class="muted" style="font-size:13px;margin-top:12px;max-width:260px">{$this->e($this->d['sub'])}</p></div>
    {$cols}
  </div>
  <div class="wrap" style="padding:0 28px 24px"><div class="muted" style="border-top:1px solid var(--border);padding-top:16px;display:flex;justify-content:space-between;gap:14px;font-size:12.5px;flex-wrap:wrap"><span>© 2026 {$this->e($this->brand)}</span><span>Privacy · Terms · Complaints</span></div></div>
</footer>
HTML;
    }

    private function footerSimple(): string
    {
        return <<<HTML
<footer style="border-top:1px solid var(--border)">
  <div class="wrap" style="padding:26px 28px;display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap">
    <div class="logo" style="font-size:16px"><i style="width:22px;height:22px"></i>{$this->e($this->brand)}</div>
    <div class="muted" style="display:flex;gap:20px;font-size:12.5px"><a href="#">Privacy</a><a href="#">Terms</a><a href="#">Contact</a><span>© 2026</span></div>
  </div>
</footer>
HTML;
    }

    // --------------------------------------------------------------- helpers

    /** Wraps the closing words of a headline so the accent lands on something. */
    private function highlight(string $text): string
    {
        $parts = preg_split('/\s+/', trim($text)) ?: [];
        if (count($parts) < 2) {
            return $this->e($text);
        }
        $tail = array_splice($parts, -2);

        return $this->e(implode(' ', $parts)).' <em class="hl">'.$this->e(implode(' ', $tail)).'</em>';
    }

    /** Domain items, rotated per generation so the content differs too. */
    private function pickItems(int $count, int $offset = 0): array
    {
        $items = $this->d['items'];
        shuffle($items);

        return array_slice($items, $offset % max(1, count($items)), $count);
    }

    /** Nudges numbers in copy so two runs of the same brief aren't identical. */
    private function jitter(string $value): string
    {
        if (! preg_match('/^([^\d]*)([\d,]+)(.*)$/', $value, $m)) {
            return $value;
        }

        $number = (int) str_replace(',', '', $m[2]);
        if ($number < 8) {
            return $value;
        }

        $delta = max(1, (int) round($number * (mt_rand(15, 60) / 1000)));

        return $m[1].number_format($number + (mt_rand(0, 1) ? $delta : -$delta)).$m[3];
    }

    private function webDashboard(): string
    {
        $d = $this->d;
        $menu = '';
        foreach (array_merge(['Overview'], $d['nav'], ['Settings']) as $i => $item) {
            $style = $i === 0 ? "background:color-mix(in srgb,var(--accent) 14%,transparent);color:var(--accent);font-weight:600" : '';
            $menu .= "<a href='#' style='display:block;padding:10px 14px;border-radius:var(--r);{$style}'>{$this->e($item)}</a>";
        }
        $kpis = '';
        foreach ($d['stats'] as [$label, $value, $delta]) {
            $cls = str_starts_with($delta, '-') ? 'down' : 'up';
            $kpis .= "<div class='card' style='padding:20px'><div class='muted' style='font-size:13px'>{$this->e($label)}</div><div style='font-size:28px;font-weight:800;margin:6px 0'>{$this->e($value)}</div><span class='{$cls}' style='font-size:13px;font-weight:600'>{$this->e($delta)} vs last week</span></div>";
        }
        $bars = '';
        foreach ([42, 58, 50, 71, 64, 86, 78, 92, 70, 88, 95, 83] as $h) {
            $bars .= "<div style='flex:1;height:{$h}%;background:var(--accent);opacity:.85;border-radius:6px 6px 0 0'></div>";
        }
        $statuses = [['Completed', 'up'], ['Pending', ''], ['Completed', 'up'], ['Cancelled', 'down'], ['Processing', '']];
        $people = ['Nadia Islam', 'Arif Hossain', 'Sara Khan', 'Tanvir Ahmed', 'Mitu Akter'];
        $rows = '';
        foreach (array_slice($d['items'], 0, 5) as $i => [$name, $meta]) {
            [$st, $cls] = $statuses[$i];
            $rows .= "<tr><td style='font-weight:600'>{$this->e($name)}</td><td class='muted'>{$people[$i]}</td><td>{$this->e($meta)}</td><td><span class='badge {$cls}'>{$st}</span></td></tr>";
        }
        $head = implode('', array_map(fn ($h) => "<th>{$this->e($h)}</th>", $d['tableHead']));

        return <<<HTML
<div style="display:grid;grid-template-columns:240px 1fr;min-height:100vh">
  <aside style="border-right:1px solid var(--border);padding:22px 16px;background:var(--surface)">
    <div class="logo" style="padding:0 10px 26px"><i></i>{$this->e($this->brand)}</div>
    <nav style="display:flex;flex-direction:column;gap:4px;font-size:15px">{$menu}</nav>
  </aside>
  <main style="padding:28px 34px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:26px">
      <div><h1 style="font-size:26px">Good morning, Alex 👋</h1><p class="muted">Here's what's happening with your {$this->e($d['entity'])}s today.</p></div>
      <div style="display:flex;gap:10px;align-items:center"><input placeholder="Search…" style="width:240px"><a class="btn" href="#">+ New {$this->e($d['entity'])}</a></div>
    </div>
    <div class="grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">{$kpis}</div>
    <div class="grid" style="grid-template-columns:2fr 1fr;margin-bottom:20px">
      <div class="card" style="padding:22px"><div style="display:flex;justify-content:space-between;margin-bottom:16px"><strong>Performance</strong><span class="muted" style="font-size:13px">Last 12 months</span></div><div style="display:flex;align-items:flex-end;gap:10px;height:200px">{$bars}</div></div>
      <div class="card" style="padding:22px"><strong>Quick actions</strong><div style="display:flex;flex-direction:column;gap:10px;margin-top:16px">
        <a class="btn ghost" href="#">Export report</a><a class="btn ghost" href="#">Invite teammate</a><a class="btn ghost" href="#">View analytics</a></div></div>
    </div>
    <div class="card"><div style="padding:18px 16px;display:flex;justify-content:space-between"><strong>Recent {$this->e($d['entity'])}s</strong><span class="muted" style="font-size:13px">View all</span></div>
      <table><thead><tr>{$head}</tr></thead><tbody>{$rows}</tbody></table></div>
  </main>
</div>
HTML;
    }

    private function webLogin(): string
    {
        $d = $this->d;
        $points = '';
        foreach ($d['features'] as [$icon, $title]) {
            $points .= "<div style='display:flex;gap:12px;align-items:center;margin-bottom:14px'><span style='font-size:22px'>{$icon}</span><span>{$this->e($title)}</span></div>";
        }

        return <<<HTML
<div style="display:grid;grid-template-columns:1fr 1fr;min-height:100vh">
  <div style="background:var(--accent);color:#fff;padding:56px;display:flex;flex-direction:column;justify-content:space-between">
    <div class="logo" style="color:#fff"><i style="background:#fff"></i>{$this->e($this->brand)}</div>
    <div><h1 style="font-size:42px;margin-bottom:16px">{$this->e($d['tagline'])}</h1><p style="opacity:.85;font-size:17px;margin-bottom:30px">{$this->e($d['sub'])}</p>{$points}</div>
    <p style="opacity:.7;font-size:14px">© 2026 {$this->e($this->brand)}</p>
  </div>
  <div style="display:flex;align-items:center;justify-content:center;padding:40px">
    <div style="width:100%;max-width:400px">
      <h2 style="font-size:30px;margin-bottom:6px">Welcome back</h2>
      <p class="muted" style="margin-bottom:28px">Log in to continue to {$this->e($this->brand)}.</p>
      <div style="display:flex;flex-direction:column;gap:14px">
        <label style="font-size:14px;font-weight:600">Email<input style="margin-top:6px" placeholder="you@example.com"></label>
        <label style="font-size:14px;font-weight:600">Password<input style="margin-top:6px" type="password" value="password"></label>
        <div style="display:flex;justify-content:space-between;font-size:14px"><span class="muted">☐ Remember me</span><a style="color:var(--accent);font-weight:600" href="#">Forgot password?</a></div>
        <a class="btn" href="#" style="margin-top:6px">Log in</a>
        <div class="muted" style="text-align:center;font-size:13px">or continue with</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px"><a class="btn ghost" href="#">Google</a><a class="btn ghost" href="#">Phone OTP</a></div>
        <p class="muted" style="text-align:center;font-size:14px;margin-top:8px">New here? <a style="color:var(--accent);font-weight:600" href="#">Create an account</a></p>
      </div>
    </div>
  </div>
</div>
HTML;
    }

    private function webPricing(): string
    {
        $tiers = [['Starter', '0', 'For individuals getting started', false], ['Pro', '19', 'For growing teams and power users', true], ['Business', '49', 'Advanced controls and support', false]];
        $perks = array_merge(array_map(fn ($f) => $f[1], $this->d['features']), ['Priority support', 'Unlimited history', 'Custom branding']);
        $cards = '';
        foreach ($tiers as $i => [$name, $price, $desc, $hot]) {
            $list = '';
            foreach (array_slice($perks, 0, 3 + $i * 1 + ($hot ? 1 : 0)) as $p) {
                $list .= "<li style='display:flex;gap:10px;margin-bottom:10px'><span style='color:var(--accent);font-weight:800'>✓</span>{$this->e($p)}</li>";
            }
            $border = $hot ? 'border:2px solid var(--accent);transform:translateY(-10px)' : '';
            $badge = $hot ? "<span class='badge' style='margin-bottom:12px'>Most popular</span>" : "<span style='display:block;height:33px'></span>";
            $btn = $hot ? 'btn' : 'btn ghost';
            $cards .= "<div class='card' style='padding:32px;{$border}'>{$badge}<h3 style='font-size:22px'>{$name}</h3><p class='muted' style='margin:6px 0 20px'>{$desc}</p><div style='font-size:46px;font-weight:800;margin-bottom:22px'>\${$price}<span class='muted' style='font-size:16px;font-weight:500'>/month</span></div><a class='{$btn}' style='width:100%;margin-bottom:24px' href='#'>Choose {$name}</a><ul style='list-style:none'>{$list}</ul></div>";
        }

        return $this->nav().<<<HTML
<section class="wrap" style="padding:72px 28px;text-align:center">
  <span class="badge">Pricing</span>
  <h1 style="font-size:46px;margin:14px 0 12px">Simple plans for every stage</h1>
  <p class="muted" style="font-size:18px;margin-bottom:22px">Start free. Upgrade when {$this->e($this->brand)} becomes part of your day.</p>
  <div style="display:inline-flex;gap:4px;padding:4px;border:1px solid var(--border);border-radius:999px;margin-bottom:54px"><span class="chip on">Monthly</span><span class="chip" style="border:0">Yearly · save 20%</span></div>
  <div class="grid" style="grid-template-columns:repeat(3,1fr);text-align:left;align-items:start">{$cards}</div>
</section>
HTML;
    }

    private function webCatalog(): string
    {
        $d = $this->d;
        $chips = '';
        foreach ($d['chips'] as $i => $c) {
            $chips .= "<label style='display:flex;gap:10px;align-items:center;padding:6px 0'><span style='width:16px;height:16px;border-radius:4px;border:1px solid var(--border);".($i === 1 ? 'background:var(--accent)' : '')."'></span>{$this->e($c)}</label>";
        }
        $cards = '';
        foreach ($d['items'] as $i => [$name, $meta]) {
            $icon = $d['features'][$i % 3][0];
            $cards .= "<div class='card' style='padding:14px'><div class='ph' style='height:190px;margin-bottom:14px;position:relative'>{$icon}<span style='position:absolute;top:10px;right:10px;background:var(--surface);border-radius:999px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:15px'>♡</span></div><div style='font-weight:600;margin-bottom:4px'>{$this->e($name)}</div><div style='display:flex;justify-content:space-between;align-items:center'><span style='font-weight:700;color:var(--accent)'>{$this->e($meta)}</span><span class='muted' style='font-size:13px'>★ 4.".(9 - $i % 4).'</span></div></div>';
        }

        return $this->nav().<<<HTML
<div class="wrap" style="padding:30px 28px">
  <div class="muted" style="font-size:14px;margin-bottom:8px">Home / {$this->e($d['nav'][0])}</div>
  <div style="display:flex;justify-content:space-between;align-items:end;margin-bottom:24px"><h1 style="font-size:34px">{$this->e($d['nav'][0])}</h1>
  <div style="display:flex;gap:10px"><input placeholder="Search {$this->e(strtolower($d['nav'][0]))}…" style="width:280px"><span class="chip">Sort: Popular ▾</span></div></div>
  <div style="display:grid;grid-template-columns:220px 1fr;gap:30px">
    <aside><div style="font-weight:700;margin-bottom:10px">Categories</div>{$chips}
      <div style="font-weight:700;margin:22px 0 10px">Price range</div><div style="height:6px;border-radius:9px;background:var(--border);position:relative"><div style="position:absolute;left:15%;right:30%;height:6px;background:var(--accent);border-radius:9px"></div></div></aside>
    <div class="grid" style="grid-template-columns:repeat(3,1fr)">{$cards}</div>
  </div>
</div>
HTML;
    }

    // ----------------------------------------------------------------- mobile

    private function statusBar(): string
    {
        return "<div style='display:flex;justify-content:space-between;padding:12px 22px 6px;font-size:13px;font-weight:600'><span>9:41</span><span>▂▄▆ ᯤ ▮</span></div>";
    }

    private function tabBar(int $active = 0): string
    {
        $tabs = [['⌂', 'Home'], ['⌕', 'Explore'], ['♡', 'Saved'], ['◯', 'Profile']];
        $html = '';
        foreach ($tabs as $i => [$icon, $label]) {
            $color = $i === $active ? 'color:var(--accent);font-weight:700' : 'color:var(--muted)';
            $html .= "<div style='text-align:center;font-size:11px;{$color}'><div style='font-size:20px'>{$icon}</div>{$label}</div>";
        }

        return "<nav style='position:sticky;bottom:0;display:grid;grid-template-columns:repeat(4,1fr);padding:10px 8px 18px;background:var(--surface);border-top:1px solid var(--border)'>{$html}</nav>";
    }

    private function mobileHome(): string
    {
        $d = $this->d;
        $chips = '';
        foreach ($d['chips'] as $i => $c) {
            $chips .= "<span class='chip".($i === 0 ? ' on' : '')."'>{$this->e($c)}</span>";
        }
        $grid = '';
        foreach (array_slice($d['items'], 0, 4) as $i => [$name, $meta]) {
            $grid .= "<div class='card' style='padding:10px'><div class='ph' style='height:110px;margin-bottom:10px'>{$d['features'][$i % 3][0]}</div><div style='font-weight:600;font-size:14px;line-height:1.3'>{$this->e($name)}</div><div style='color:var(--accent);font-weight:700;font-size:14px;margin-top:4px'>{$this->e($meta)}</div></div>";
        }
        $list = '';
        foreach (array_slice($d['items'], 4, 2) as $i => [$name, $meta]) {
            $list .= "<div class='card' style='padding:12px;display:flex;gap:12px;align-items:center'><div class='ph' style='width:56px;height:56px;font-size:22px'>{$d['features'][$i][0]}</div><div style='flex:1'><div style='font-weight:600;font-size:14px'>{$this->e($name)}</div><div class='muted' style='font-size:13px'>{$this->e($meta)}</div></div><span style='color:var(--accent);font-size:20px'>›</span></div>";
        }

        return $this->statusBar().<<<HTML
<div style="padding:8px 20px 20px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
    <div><div class="muted" style="font-size:13px">Hello, Alex 👋</div><div style="font-size:22px;font-weight:800">{$this->e($this->brand)}</div></div>
    <div style="width:42px;height:42px;border-radius:50%;background:var(--accent);opacity:.9"></div>
  </div>
  <input placeholder="⌕  Search {$this->e(strtolower($d['nav'][0]))}…" style="margin-bottom:18px">
  <div style="border-radius:var(--r);background:var(--accent);color:#fff;padding:20px;margin-bottom:20px;position:relative;overflow:hidden">
    <div style="position:absolute;right:-30px;top:-30px;width:130px;height:130px;border-radius:50%;background:rgba(255,255,255,.15)"></div>
    <div style="font-size:12px;opacity:.85;font-weight:600;text-transform:uppercase;letter-spacing:.06em">Featured</div>
    <div style="font-size:20px;font-weight:800;margin:6px 0 12px;max-width:220px">{$this->e($d['tagline'])}</div>
    <span class="btn" style="background:#fff;color:var(--accent);padding:9px 16px;font-size:13px">{$this->e($d['cta'])}</span>
  </div>
  <div style="display:flex;gap:8px;overflow:hidden;margin-bottom:20px">{$chips}</div>
  <div style="display:flex;justify-content:space-between;margin-bottom:12px"><strong>Popular</strong><span class="muted" style="font-size:13px">See all</span></div>
  <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">{$grid}</div>
  <div style="display:flex;justify-content:space-between;margin-bottom:12px"><strong>Recommended</strong><span class="muted" style="font-size:13px">See all</span></div>
  <div style="display:flex;flex-direction:column;gap:10px">{$list}</div>
</div>
HTML.$this->tabBar(0);
    }

    private function mobileLogin(): string
    {
        return $this->statusBar().<<<HTML
<div style="padding:30px 24px;min-height:calc(100vh - 40px);display:flex;flex-direction:column">
  <div class="ph" style="height:220px;margin-bottom:30px;font-size:64px;background:var(--hero)">{$this->d['features'][0][0]}</div>
  <h1 style="font-size:28px;margin-bottom:8px">Welcome to {$this->e($this->brand)}</h1>
  <p class="muted" style="margin-bottom:26px">{$this->e($this->d['sub'])}</p>
  <div style="display:flex;flex-direction:column;gap:12px">
    <input placeholder="Phone or email">
    <input type="password" placeholder="Password">
    <a style="align-self:flex-end;font-size:13px;color:var(--accent);font-weight:600" href="#">Forgot password?</a>
    <a class="btn" href="#" style="padding:15px">Log in</a>
    <div class="muted" style="text-align:center;font-size:13px;margin:6px 0">or</div>
    <a class="btn ghost" href="#" style="padding:15px">Continue with Google</a>
  </div>
  <p class="muted" style="text-align:center;font-size:14px;margin-top:auto;padding-top:24px">Don't have an account? <a style="color:var(--accent);font-weight:700" href="#">Sign up</a></p>
</div>
HTML;
    }

    private function mobileDetail(): string
    {
        $d = $this->d;
        [$name, $meta] = $d['items'][0];
        $feats = '';
        foreach ($d['features'] as [$icon, $title, $text]) {
            $feats .= "<div style='display:flex;gap:12px;margin-bottom:14px'><div class='ph' style='width:40px;height:40px;font-size:18px;flex:none'>{$icon}</div><div><div style='font-weight:600;font-size:14px'>{$this->e($title)}</div><div class='muted' style='font-size:13px'>{$this->e($text)}</div></div></div>";
        }
        $opts = '';
        foreach (array_slice($d['chips'], 1, 4) as $i => $c) {
            $opts .= "<span class='chip".($i === 0 ? ' on' : '')."'>{$this->e($c)}</span>";
        }

        return $this->statusBar().<<<HTML
<div style="position:relative">
  <div class="ph" style="height:320px;border-radius:0;font-size:90px">{$d['features'][0][0]}</div>
  <div style="position:absolute;top:14px;left:16px;right:16px;display:flex;justify-content:space-between"><span class="card" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:50%">‹</span><span class="card" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:50%">♡</span></div>
</div>
<div style="padding:22px 20px 110px;margin-top:-24px;background:var(--bg);border-radius:24px 24px 0 0;position:relative">
  <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:6px"><h1 style="font-size:23px;max-width:250px">{$this->e($name)}</h1><span class="badge">★ 4.9</span></div>
  <p class="muted" style="font-size:14px;margin-bottom:18px">{$this->e($d['sub'])}</p>
  <div style="font-weight:700;margin-bottom:10px">Options</div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px">{$opts}</div>
  {$feats}
</div>
<div style="position:fixed;bottom:0;left:0;right:0;max-width:430px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;padding:16px 20px 24px;background:var(--surface);border-top:1px solid var(--border)">
  <div><div class="muted" style="font-size:12px">{$this->e($d['metaLabel'] ?? 'Price')}</div><div style="font-size:22px;font-weight:800">{$this->e($meta)}</div></div>
  <a class="btn" href="#" style="padding:14px 28px">{$this->e($d['cta'])}</a>
</div>
HTML;
    }

    private function mobileProfile(): string
    {
        $d = $this->d;
        $rows = '';
        foreach ([['👤', 'Personal information'], ['🔔', 'Notifications'], ['💳', 'Payment methods'], ['🔒', 'Privacy & security'], ['🌐', 'Language · English'], ['❓', 'Help center']] as [$icon, $label]) {
            $rows .= "<div style='display:flex;align-items:center;gap:14px;padding:14px 4px;border-bottom:1px solid var(--border)'><span style='font-size:18px'>{$icon}</span><span style='flex:1;font-size:15px'>{$label}</span><span class='muted'>›</span></div>";
        }
        $stats = '';
        foreach (array_slice($d['stats'], 0, 3) as [$label, $value]) {
            $stats .= "<div style='text-align:center'><div style='font-weight:800;font-size:18px'>{$this->e($value)}</div><div class='muted' style='font-size:12px'>{$this->e($label)}</div></div>";
        }

        return $this->statusBar().<<<HTML
<div style="padding:10px 20px 24px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:22px"><strong style="font-size:18px">Profile</strong><span>⚙︎</span></div>
  <div style="text-align:center;margin-bottom:22px">
    <div style="width:92px;height:92px;border-radius:50%;margin:0 auto 12px;background:linear-gradient(135deg,var(--accent),color-mix(in srgb,var(--accent) 30%,#fff));display:flex;align-items:center;justify-content:center;color:#fff;font-size:34px;font-weight:800">S</div>
    <div style="font-size:20px;font-weight:800">Alex Rahman</div><div class="muted" style="font-size:14px">Member since 2024 · Dhaka</div>
  </div>
  <div class="card" style="display:grid;grid-template-columns:repeat(3,1fr);padding:16px 8px;margin-bottom:22px">{$stats}</div>
  <div>{$rows}</div>
  <a class="btn ghost" href="#" style="width:100%;margin-top:22px;color:#dc2626">Log out</a>
</div>
HTML.$this->tabBar(3);
    }

    private function mobileDashboard(): string
    {
        $d = $this->d;
        $tiles = '';
        foreach ($d['stats'] as [$label, $value, $delta]) {
            $cls = str_starts_with($delta, '-') ? 'down' : 'up';
            $tiles .= "<div class='card' style='padding:14px'><div class='muted' style='font-size:12px'>{$this->e($label)}</div><div style='font-size:20px;font-weight:800;margin:4px 0'>{$this->e($value)}</div><span class='{$cls}' style='font-size:12px;font-weight:600'>{$this->e($delta)}</span></div>";
        }
        $bars = '';
        foreach ([40, 65, 50, 80, 60, 92, 74] as $i => $h) {
            $day = ['M', 'T', 'W', 'T', 'F', 'S', 'S'][$i];
            $bars .= "<div style='flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;justify-content:flex-end'><div style='width:100%;height:{$h}%;background:var(--accent);border-radius:8px;opacity:".($i === 5 ? '1' : '.45')."'></div><span class='muted' style='font-size:11px'>{$day}</span></div>";
        }
        $list = '';
        foreach (array_slice($d['items'], 0, 3) as $i => [$name, $meta]) {
            $list .= "<div style='display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)'><div class='ph' style='width:42px;height:42px;font-size:18px'>{$d['features'][$i][0]}</div><div style='flex:1;font-size:14px;font-weight:600'>{$this->e($name)}</div><div style='font-size:14px;font-weight:700'>{$this->e($meta)}</div></div>";
        }

        return $this->statusBar().<<<HTML
<div style="padding:8px 20px 20px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px"><div><div class="muted" style="font-size:13px">Overview</div><div style="font-size:22px;font-weight:800">This week</div></div><span class="chip">Oct ▾</span></div>
  <div class="grid" style="grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px">{$tiles}</div>
  <div class="card" style="padding:16px;margin-bottom:18px"><strong style="font-size:14px">Activity</strong><div style="display:flex;gap:8px;height:140px;margin-top:12px">{$bars}</div></div>
  <div style="display:flex;justify-content:space-between;margin-bottom:4px"><strong>Recent</strong><span class="muted" style="font-size:13px">See all</span></div>
  {$list}
</div>
HTML.$this->tabBar(1);
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
