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
        'agriculture' => 'Agritech', 'services' => 'Home services', 'social' => 'Community', 'productivity' => 'Productivity',
    ];

    private array $d;   // domain profile

    private array $t;   // theme

    private string $brand;

    public function render(string $prompt, string $platform, string $screenType, string $style, ?string $accent, ?string $categorySlug = null): string
    {
        $this->d = DomainProfiles::forPrompt($prompt, $categorySlug);
        $this->t = self::STYLES[$style] ?? self::STYLES['modern'];
        if ($accent && preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
            $this->t['accent'] = $accent;
        }
        $this->brand = $this->d['brand'][abs(crc32($prompt)) % count($this->d['brand'])];

        if ($platform === 'mobile') {
            $layout = self::MOBILE_MAP[$screenType] ?? 'home';
            $body = $this->{'mobile'.ucfirst($layout)}();
        } else {
            $layout = self::WEB_MAP[$screenType] ?? 'landing';
            $body = $this->{'web'.ucfirst($layout)}();
        }

        return $this->document($body, $platform === 'mobile');
    }

    // ------------------------------------------------------------------ shell

    private function document(string $body, bool $mobile): string
    {
        $t = $this->t;
        $title = $this->e($this->brand);
        $maxW = $mobile ? '430px' : 'none';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<style>
:root{--bg:{$t['bg']};--surface:{$t['surface']};--text:{$t['text']};--muted:{$t['muted']};--border:{$t['border']};--accent:{$t['accent']};--r:{$t['radius']};--hero:{$t['hero']}}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:{$t['font']};background:var(--bg);color:var(--text);line-height:1.5;-webkit-font-smoothing:antialiased;max-width:{$maxW};margin:0 auto;min-height:100vh}
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
h1,h2,h3{line-height:1.15;letter-spacing:-.02em}
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

    private function nav(): string
    {
        $links = implode('', array_map(fn ($l) => '<a class="muted" href="#">'.$this->e($l).'</a>', $this->d['nav']));

        return <<<HTML
<header style="border-bottom:1px solid var(--border)">
  <div class="wrap" style="display:flex;align-items:center;justify-content:space-between;height:70px">
    <div class="logo"><i></i>{$this->e($this->brand)}</div>
    <nav style="display:flex;gap:28px;font-size:15px">{$links}</nav>
    <div style="display:flex;gap:10px"><a class="btn ghost" href="#">Log in</a><a class="btn" href="#">{$this->e($this->d['cta'])}</a></div>
  </div>
</header>
HTML;
    }

    // -------------------------------------------------------------------- web

    private function webLanding(): string
    {
        $d = $this->d;
        $features = '';
        foreach ($d['features'] as [$icon, $title, $text]) {
            $features .= "<div class='card' style='padding:28px'><div class='ph' style='width:52px;height:52px;font-size:24px;margin-bottom:18px'>{$icon}</div><h3 style='font-size:19px;margin-bottom:6px'>{$this->e($title)}</h3><p class='muted'>{$this->e($text)}</p></div>";
        }
        $items = '';
        foreach (array_slice($d['items'], 0, 3) as $i => [$name, $meta]) {
            $items .= "<div class='card' style='padding:14px'><div class='ph' style='height:150px;margin-bottom:14px'>{$d['features'][$i % 3][0]}</div><div style='font-weight:600'>{$this->e($name)}</div><div class='muted' style='font-size:14px'>{$this->e($meta)}</div></div>";
        }
        $stats = '';
        foreach ([['50k+', 'Happy users'], ['4.9★', 'Average rating'], ['24/7', 'Support']] as [$value, $label]) {
            $stats .= "<div><div style='font-size:30px;font-weight:800'>{$value}</div><div class='muted' style='font-size:14px'>{$label}</div></div>";
        }

        return $this->nav().<<<HTML
<section style="background:var(--hero)">
  <div class="wrap" style="display:grid;grid-template-columns:1.1fr .9fr;gap:56px;align-items:center;padding:84px 28px">
    <div>
      <span class="badge">New · {$this->e(self::LABELS[$d['key']] ?? 'Launch')}</span>
      <h1 style="font-size:54px;margin:18px 0 18px">{$this->e($d['tagline'])}</h1>
      <p class="muted" style="font-size:19px;max-width:520px;margin-bottom:30px">{$this->e($d['sub'])}</p>
      <div style="display:flex;gap:12px"><a class="btn" href="#">{$this->e($d['cta'])} →</a><a class="btn ghost" href="#">Watch demo</a></div>
      <div style="display:flex;gap:36px;margin-top:44px">{$stats}</div>
    </div>
    <div class="card" style="padding:22px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><strong>Popular right now</strong><span class="muted" style="font-size:13px">See all</span></div>
      <div class="grid" style="grid-template-columns:1fr 1fr 1fr;gap:12px">{$items}</div>
    </div>
  </div>
</section>
<section class="wrap" style="padding:84px 28px">
  <h2 style="font-size:36px;text-align:center;margin-bottom:10px">Why people choose {$this->e($this->brand)}</h2>
  <p class="muted" style="text-align:center;margin-bottom:44px">Built around what actually matters to you.</p>
  <div class="grid" style="grid-template-columns:repeat(3,1fr)">{$features}</div>
</section>
<section class="wrap" style="padding:0 28px 84px">
  <div class="card" style="padding:48px;display:flex;justify-content:space-between;align-items:center;background:var(--accent);color:#fff;border:0">
    <div><h2 style="font-size:30px;margin-bottom:6px">Ready to get started?</h2><p style="opacity:.85">Join thousands already using {$this->e($this->brand)}.</p></div>
    <a class="btn" style="background:#fff;color:var(--accent)" href="#">{$this->e($d['cta'])}</a>
  </div>
</section>
<footer style="border-top:1px solid var(--border)"><div class="wrap muted" style="padding:28px;display:flex;justify-content:space-between;font-size:14px"><span>© 2026 {$this->e($this->brand)}</span><span>Privacy · Terms · Contact</span></div></footer>
HTML;
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
