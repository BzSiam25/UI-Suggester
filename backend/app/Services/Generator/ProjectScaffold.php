<?php

namespace App\Services\Generator;

use App\Models\UiGeneration;

/**
 * The files around the screens Qwen writes: entry point, router, package /
 * build config, a shared layout and a README. These follow fixed conventions,
 * so they are written here instead of spending model time on them.
 */
class ProjectScaffold
{
    /**
     * @param  array<string, mixed>  $framework
     * @param  array<int, array{slug: string, name: string}>  $screens
     * @param  array<string, mixed>  $plan
     * @return array<int, array{path: string, language: string, screen: ?string, content: string}>
     */
    public function files(array $framework, array $screens, array $plan, UiGeneration $generation): array
    {
        $brand = $plan['brand'] ?? 'App';
        $package = $this->packageName($brand);
        $s = array_map(fn ($x) => $x + [
            'pascal' => FrameworkCatalog::pascal($x['slug']),
            'kebab' => FrameworkCatalog::path('{slug}', $x['slug']),
            'snake' => FrameworkCatalog::path('{snake}', $x['slug']),
        ], $screens);

        $files = match ($framework['slug']) {
            'react' => $this->react($s, $brand, $package, false),
            'react-ts' => $this->react($s, $brand, $package, true),
            'vue' => $this->vue($s, $brand, $package),
            'nextjs' => $this->next($s, $brand, $package),
            'angular' => $this->angular($s, $brand),
            'blade' => $this->blade($s, $brand),
            'django' => $this->django($s, $brand),
            'flask' => $this->flask($s, $brand),
            'flutter' => $this->flutter($s, $brand, $package),
            'compose' => $this->compose($s, $brand),
            'swiftui' => $this->swiftui($s, $brand),
            'html-css', 'tailwind', 'bootstrap', 'php' => $this->index($s, $brand, $framework['slug'] === 'php' ? 'php' : 'html'),
            default => [],
        };

        $files[] = $this->file('README.md', $this->readme($framework, $s, $plan, $generation));

        return $files;
    }

    // -------------------------------------------------------------- frameworks

    private function react(array $s, string $brand, string $package, bool $ts): array
    {
        $ext = $ts ? 'tsx' : 'jsx';
        $imports = implode("\n", array_map(fn ($x) => "import {$x['pascal']} from './pages/{$x['pascal']}'", $s));
        $routes = implode("\n", array_map(fn ($x, $i) => "        <Route path=\"".($i === 0 ? '/' : "/{$x['kebab']}")."\" element={<{$x['pascal']} />} />", $s, array_keys($s)));
        $links = implode("\n", array_map(fn ($x, $i) => "        <NavLink to=\"".($i === 0 ? '/' : "/{$x['kebab']}")."\">{$x['name']}</NavLink>", $s, array_keys($s)));

        $deps = [
            'name' => $package, 'private' => true, 'version' => '0.1.0', 'type' => 'module',
            'scripts' => ['dev' => 'vite', 'build' => 'vite build', 'preview' => 'vite preview'],
            'dependencies' => ['react' => '^18.3.1', 'react-dom' => '^18.3.1', 'react-router-dom' => '^6.28.0'],
            'devDependencies' => ['@vitejs/plugin-react' => '^4.3.4', 'vite' => '^5.4.11'] + ($ts ? ['typescript' => '^5.6.3', '@types/react' => '^18.3.12', '@types/react-dom' => '^18.3.1'] : []),
        ];

        $files = [
            $this->file('package.json', json_encode($deps, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"),
            $this->file('vite.config.'.($ts ? 'ts' : 'js'), "import { defineConfig } from 'vite'\nimport react from '@vitejs/plugin-react'\n\nexport default defineConfig({ plugins: [react()] })\n"),
            $this->file('index.html', $this->htmlShell($brand, "<div id=\"root\"></div>\n    <script type=\"module\" src=\"/src/main.{$ext}\"></script>")),
            $this->file("src/main.{$ext}", "import React from 'react'\nimport ReactDOM from 'react-dom/client'\nimport { BrowserRouter } from 'react-router-dom'\nimport App from './App'\n\nReactDOM.createRoot(document.getElementById('root')".($ts ? '!' : '').").render(\n  <React.StrictMode>\n    <BrowserRouter>\n      <App />\n    </BrowserRouter>\n  </React.StrictMode>,\n)\n"),
            $this->file("src/App.{$ext}", "import { NavLink, Route, Routes } from 'react-router-dom'\n{$imports}\n\n// Screen switcher for development; each page also has its own navigation.\nexport default function App() {\n  return (\n    <>\n      <nav className=\"dev-screens\">\n{$links}\n      </nav>\n      <Routes>\n{$routes}\n      </Routes>\n    </>\n  )\n}\n"),
        ];
        if ($ts) {
            $files[] = $this->file('tsconfig.json', json_encode(['compilerOptions' => ['target' => 'ES2020', 'lib' => ['ES2020', 'DOM', 'DOM.Iterable'], 'module' => 'ESNext', 'moduleResolution' => 'bundler', 'jsx' => 'react-jsx', 'strict' => true, 'skipLibCheck' => true, 'noEmit' => true], 'include' => ['src']], JSON_PRETTY_PRINT)."\n");
        }

        return $files;
    }

    private function vue(array $s, string $brand, string $package): array
    {
        $imports = implode("\n", array_map(fn ($x) => "import {$x['pascal']}View from './views/{$x['pascal']}View.vue'", $s));
        $routes = implode(",\n", array_map(fn ($x, $i) => "    { path: '".($i === 0 ? '/' : "/{$x['kebab']}")."', name: '{$x['kebab']}', component: {$x['pascal']}View }", $s, array_keys($s)));

        return [
            $this->file('package.json', json_encode([
                'name' => $package, 'private' => true, 'version' => '0.1.0', 'type' => 'module',
                'scripts' => ['dev' => 'vite', 'build' => 'vite build', 'preview' => 'vite preview'],
                'dependencies' => ['vue' => '^3.5.12', 'vue-router' => '^4.4.5'],
                'devDependencies' => ['@vitejs/plugin-vue' => '^5.1.4', 'vite' => '^5.4.11'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"),
            $this->file('vite.config.js', "import { defineConfig } from 'vite'\nimport vue from '@vitejs/plugin-vue'\n\nexport default defineConfig({ plugins: [vue()] })\n"),
            $this->file('index.html', $this->htmlShell($brand, "<div id=\"app\"></div>\n    <script type=\"module\" src=\"/src/main.js\"></script>")),
            $this->file('src/main.js', "import { createApp } from 'vue'\nimport App from './App.vue'\nimport router from './router'\n\ncreateApp(App).use(router).mount('#app')\n"),
            $this->file('src/router.js', "import { createRouter, createWebHistory } from 'vue-router'\n{$imports}\n\nexport default createRouter({\n  history: createWebHistory(),\n  routes: [\n{$routes},\n  ],\n})\n"),
            $this->file('src/App.vue', "<template>\n  <RouterView />\n</template>\n"),
        ];
    }

    private function next(array $s, string $brand, string $package): array
    {
        $first = $s[0] ?? null;

        return [
            $this->file('package.json', json_encode([
                'name' => $package, 'private' => true, 'version' => '0.1.0',
                'scripts' => ['dev' => 'next dev', 'build' => 'next build', 'start' => 'next start'],
                'dependencies' => ['next' => '^15.0.3', 'react' => '^18.3.1', 'react-dom' => '^18.3.1'],
                'devDependencies' => ['typescript' => '^5.6.3', '@types/node' => '^22.9.0', '@types/react' => '^18.3.12'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"),
            $this->file('app/layout.tsx', "import type { Metadata } from 'next'\n\nexport const metadata: Metadata = { title: '".addslashes($brand)."' }\n\nexport default function RootLayout({ children }: { children: React.ReactNode }) {\n  return (\n    <html lang=\"en\">\n      <body style={{ margin: 0 }}>{children}</body>\n    </html>\n  )\n}\n"),
            $this->file('app/page.tsx', $first ? "import { redirect } from 'next/navigation'\n\nexport default function Home() {\n  redirect('/{$first['kebab']}')\n}\n" : "export default function Home() { return null }\n"),
            $this->file('tsconfig.json', json_encode(['compilerOptions' => ['target' => 'ES2017', 'lib' => ['dom', 'dom.iterable', 'esnext'], 'strict' => true, 'noEmit' => true, 'module' => 'esnext', 'moduleResolution' => 'bundler', 'jsx' => 'preserve', 'skipLibCheck' => true, 'plugins' => [['name' => 'next']]], 'include' => ['**/*.ts', '**/*.tsx']], JSON_PRETTY_PRINT)."\n"),
        ];
    }

    private function angular(array $s, string $brand): array
    {
        $imports = implode("\n", array_map(fn ($x) => "import { {$x['pascal']}Component } from './{$x['kebab']}/{$x['kebab']}.component';", $s));
        $routes = implode(",\n", array_map(fn ($x) => "  { path: '{$x['kebab']}', component: {$x['pascal']}Component }", $s));
        $first = $s[0]['kebab'] ?? '';

        return [
            $this->file('src/app/app.routes.ts', "import { Routes } from '@angular/router';\n{$imports}\n\nexport const routes: Routes = [\n  { path: '', redirectTo: '{$first}', pathMatch: 'full' },\n{$routes},\n];\n"),
            $this->file('src/app/app.component.ts', "import { Component } from '@angular/core';\nimport { RouterOutlet } from '@angular/router';\n\n@Component({\n  selector: 'app-root',\n  standalone: true,\n  imports: [RouterOutlet],\n  template: '<router-outlet />',\n})\nexport class AppComponent {}\n"),
            $this->file('src/main.ts', "import { bootstrapApplication } from '@angular/platform-browser';\nimport { provideRouter } from '@angular/router';\nimport { AppComponent } from './app/app.component';\nimport { routes } from './app/app.routes';\n\nbootstrapApplication(AppComponent, { providers: [provideRouter(routes)] });\n"),
            $this->file('ANGULAR_SETUP.md', "Create a workspace with `npx @angular/cli new ".$this->packageName($brand)." --standalone --routing`, then copy `src/app` and `src/main.ts` from this folder over the generated ones.\n"),
        ];
    }

    private function blade(array $s, string $brand): array
    {
        $routes = implode("\n", array_map(fn ($x, $i) => "Route::view('".($i === 0 ? '/' : "/{$x['kebab']}")."', '{$x['kebab']}')->name('{$x['kebab']}');", $s, array_keys($s)));

        return [
            $this->file('resources/views/layouts/app.blade.php', "<!DOCTYPE html>\n<html lang=\"{{ str_replace('_', '-', app()->getLocale()) }}\">\n<head>\n    <meta charset=\"utf-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n    <title>@yield('title', '".e($brand)."')</title>\n    @stack('styles')\n</head>\n<body>\n    @yield('content')\n    @stack('scripts')\n</body>\n</html>\n"),
            $this->file('routes/web.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n// Generated screens. Merge these lines into your app's routes/web.php.\n{$routes}\n"),
        ];
    }

    private function django(array $s, string $brand): array
    {
        $views = implode("\n\n", array_map(fn ($x) => "def {$x['snake']}(request):\n    return render(request, '{$x['snake']}.html')", $s));
        $urls = implode("\n", array_map(fn ($x, $i) => "    path('".($i === 0 ? '' : "{$x['kebab']}/")."', views.{$x['snake']}, name='{$x['snake']}'),", $s, array_keys($s)));

        return [
            $this->file('templates/base.html', "{% load static %}<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n  <meta charset=\"utf-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n  <title>{% block title %}".e($brand)."{% endblock %}</title>\n  {% block styles %}{% endblock %}\n</head>\n<body>\n  {% block content %}{% endblock %}\n</body>\n</html>\n"),
            $this->file('views.py', "from django.shortcuts import render\n\n\n{$views}\n"),
            $this->file('urls.py', "from django.urls import path\n\nfrom . import views\n\nurlpatterns = [\n{$urls}\n]\n"),
        ];
    }

    private function flask(array $s, string $brand): array
    {
        $routes = implode("\n\n", array_map(fn ($x, $i) => "@app.route('".($i === 0 ? '/' : "/{$x['kebab']}")."')\ndef {$x['snake']}():\n    return render_template('{$x['snake']}.html')", $s, array_keys($s)));

        return [
            $this->file('app.py', "from flask import Flask, render_template\n\napp = Flask(__name__)\n\n\n{$routes}\n\n\nif __name__ == '__main__':\n    app.run(debug=True)\n"),
            $this->file('requirements.txt', "flask>=3.0\n"),
            $this->file('templates/base.html', "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n  <meta charset=\"utf-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n  <title>{% block title %}".e($brand)."{% endblock %}</title>\n  {% block styles %}{% endblock %}\n</head>\n<body>\n  {% block content %}{% endblock %}\n</body>\n</html>\n"),
        ];
    }

    private function flutter(array $s, string $brand, string $package): array
    {
        $imports = implode("\n", array_map(fn ($x) => "import 'screens/{$x['snake']}_screen.dart';", $s));
        $routes = implode("\n", array_map(fn ($x) => "        '/{$x['kebab']}': (_) => const {$x['pascal']}Screen(),", $s));
        $first = $s[0]['kebab'] ?? '';
        $pkg = str_replace('-', '_', $package);

        return [
            $this->file('lib/main.dart', "import 'package:flutter/material.dart';\n{$imports}\n\nvoid main() => runApp(const App());\n\nclass App extends StatelessWidget {\n  const App({super.key});\n\n  @override\n  Widget build(BuildContext context) {\n    return MaterialApp(\n      title: '".addslashes($brand)."',\n      debugShowCheckedModeBanner: false,\n      theme: ThemeData(useMaterial3: true),\n      initialRoute: '/{$first}',\n      routes: {\n{$routes}\n      },\n    );\n  }\n}\n"),
            $this->file('pubspec.yaml', "name: {$pkg}\ndescription: ".$brand." screens generated by UISpark.\npublish_to: none\nversion: 0.1.0\n\nenvironment:\n  sdk: '>=3.3.0 <4.0.0'\n\ndependencies:\n  flutter:\n    sdk: flutter\n\nflutter:\n  uses-material-design: true\n"),
        ];
    }

    private function compose(array $s, string $brand): array
    {
        $routes = implode("\n", array_map(fn ($x) => "        composable(\"{$x['kebab']}\") { {$x['pascal']}Screen(onNavigate = { nav.navigate(it) }) }", $s));
        $first = $s[0]['kebab'] ?? '';

        return [$this->file('app/src/main/java/com/example/app/MainActivity.kt', "package com.example.app\n\nimport android.os.Bundle\nimport androidx.activity.ComponentActivity\nimport androidx.activity.compose.setContent\nimport androidx.compose.material3.MaterialTheme\nimport androidx.navigation.compose.NavHost\nimport androidx.navigation.compose.composable\nimport androidx.navigation.compose.rememberNavController\nimport com.example.app.ui.screens.*\n\n// {$brand}: add androidx.navigation:navigation-compose to app/build.gradle.\nclass MainActivity : ComponentActivity() {\n    override fun onCreate(savedInstanceState: Bundle?) {\n        super.onCreate(savedInstanceState)\n        setContent {\n            MaterialTheme {\n                val nav = rememberNavController()\n                NavHost(navController = nav, startDestination = \"{$first}\") {\n{$routes}\n                }\n            }\n        }\n    }\n}\n")];
    }

    private function swiftui(array $s, string $brand): array
    {
        $tabs = implode("\n", array_map(fn ($x) => "            {$x['pascal']}View().tabItem { Label(\"".addslashes($x['name'])."\", systemImage: \"circle\") }", $s));
        $struct = preg_replace('/[^A-Za-z0-9]/', '', ucwords($brand)) ?: 'Generated';

        return [$this->file('App/'.$struct.'App.swift', "import SwiftUI\n\n@main\nstruct {$struct}App: App {\n    var body: some Scene {\n        WindowGroup {\n            TabView {\n{$tabs}\n            }\n        }\n    }\n}\n")];
    }

    private function index(array $s, string $brand, string $ext): array
    {
        $links = implode("\n", array_map(fn ($x) => "      <li><a href=\"{$x['kebab']}.{$ext}\">".e($x['name'])."</a></li>", $s));

        return [$this->file('index.html', $this->htmlShell($brand, "<main style=\"font-family:system-ui;max-width:640px;margin:48px auto;padding:0 20px\">\n    <h1>".e($brand)."</h1>\n    <ul>\n{$links}\n    </ul>\n  </main>"))];
    }

    // ----------------------------------------------------------------- helpers

    private function readme(array $framework, array $s, array $plan, UiGeneration $g): string
    {
        $run = match ($framework['slug']) {
            'react', 'react-ts', 'vue' => "npm install\nnpm run dev",
            'nextjs' => "npm install\nnpm run dev   # open /".($s[0]['kebab'] ?? ''),
            'angular' => 'See ANGULAR_SETUP.md',
            'blade' => "Copy resources/ and public/ into a Laravel app, merge routes/web.php, then:\nphp artisan serve",
            'django' => 'Copy templates/ and static/ into a Django app, merge views.py and urls.py, then: python manage.py runserver',
            'flask' => "pip install -r requirements.txt\npython app.py",
            'flutter' => "flutter create .   # once, to add platform folders\nflutter run",
            'compose' => 'Open in Android Studio inside an Empty Compose Activity project and run.',
            'swiftui' => 'Add the files to an Xcode SwiftUI app target and run.',
            'php' => 'php -S localhost:8080',
            default => 'Open the HTML files in a browser.',
        };
        $screens = implode("\n", array_map(fn ($x) => "- **{$x['name']}**: ".($plan['screens'][$x['slug']] ?? ''), $s));

        return "# {$plan['brand']}\n\n".($plan['tagline'] ? "{$plan['tagline']}\n\n" : '')
            ."Generated by UISpark with a local Qwen model.\n\n"
            ."- Language: {$framework['language']}\n- Framework: {$framework['name']}\n- Platform: {$g->platform}\n"
            .($plan['primary'] ? "- Primary colour: {$plan['primary']}\n" : '')
            ."\n## Screens\n\n{$screens}\n\n## Run\n\n```bash\n{$run}\n```\n\n"
            ."`design-preview/` holds the HTML design of every screen for quick viewing.\n\n"
            ."## Brief\n\n{$g->prompt}\n";
    }

    private function htmlShell(string $brand, string $body): string
    {
        return "<!DOCTYPE html>\n<html lang=\"en\">\n  <head>\n    <meta charset=\"UTF-8\" />\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />\n    <title>".e($brand)."</title>\n  </head>\n  <body>\n    {$body}\n  </body>\n</html>\n";
    }

    private function packageName(string $brand): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $brand) ?? '', '-'));

        return $slug !== '' ? $slug : 'generated-app';
    }

    /** @return array{path: string, language: string, screen: null, content: string} */
    private function file(string $path, string $content): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $language = match ($ext) {
            'js', 'jsx', 'mjs' => 'javascript', 'ts', 'tsx' => 'typescript', 'json' => 'json', 'vue' => 'vue',
            'md' => 'markdown', 'py' => 'python', 'dart' => 'dart', 'kt' => 'kotlin', 'swift' => 'swift',
            'yaml', 'yml' => 'yaml', 'txt' => 'text', 'php' => 'php', default => 'html',
        };

        return ['path' => $path, 'language' => $language, 'screen' => null, 'content' => $content];
    }
}
