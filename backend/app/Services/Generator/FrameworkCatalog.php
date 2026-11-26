<?php

namespace App\Services\Generator;

/**
 * Built-in languages and frameworks the generator can write code for.
 *
 * Each framework says where a screen's files go ({Name} = PascalCase,
 * {slug} = kebab-case, {snake} = snake_case), what the model should keep in
 * mind when writing it, and whether the HTML preview already is the code.
 * The seeder copies this into generator_options; frameworks users add
 * themselves get the generic profile at the bottom.
 */
class FrameworkCatalog
{
    public const LANGUAGES = [
        ['slug' => 'html-css-js', 'name' => 'HTML / CSS / JavaScript', 'description' => 'Static pages that open in any browser'],
        ['slug' => 'javascript', 'name' => 'JavaScript', 'description' => 'Component frameworks on Node and Vite'],
        ['slug' => 'typescript', 'name' => 'TypeScript', 'description' => 'Typed component frameworks'],
        ['slug' => 'php', 'name' => 'PHP', 'description' => 'Server-rendered templates'],
        ['slug' => 'python', 'name' => 'Python', 'description' => 'Django and Flask templates'],
        ['slug' => 'dart', 'name' => 'Dart', 'description' => 'Flutter apps for Android, iOS and web'],
        ['slug' => 'kotlin', 'name' => 'Kotlin', 'description' => 'Native Android with Jetpack Compose'],
        ['slug' => 'swift', 'name' => 'Swift', 'description' => 'Native iOS with SwiftUI'],
    ];

    public const FRAMEWORKS = [
        'html-css' => [
            'language' => 'html-css-js', 'name' => 'Plain HTML + CSS', 'fence' => 'html',
            'preview_is_code' => true, 'files' => ['{slug}.html'],
            'notes' => 'One standalone HTML file per screen with the CSS in a <style> tag.',
        ],
        'tailwind' => [
            'language' => 'html-css-js', 'name' => 'HTML + Tailwind CSS', 'fence' => 'html',
            'preview_is_code' => true, 'cdn' => '<script src="https://cdn.tailwindcss.com"></script>', 'files' => ['{slug}.html'],
            'notes' => 'Style with Tailwind utility classes loaded from the Tailwind Play CDN script.',
        ],
        'bootstrap' => [
            'language' => 'html-css-js', 'name' => 'HTML + Bootstrap 5', 'fence' => 'html',
            'preview_is_code' => true, 'cdn' => '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">', 'files' => ['{slug}.html'],
            'notes' => 'Use the Bootstrap 5 grid and components from the jsDelivr CDN, plus a small <style> block for brand colours.',
        ],
        'react' => [
            'language' => 'javascript', 'name' => 'React (Vite)', 'fence' => 'jsx', 'scaffold' => 'react',
            'files' => ['src/pages/{Name}.jsx', 'src/pages/{Name}.css'],
            'notes' => 'A function component `export default function {Name}()` that imports "./{Name}.css". Use className, self-close void tags, map arrays for repeated items, and Link from react-router-dom for navigation between screens.',
        ],
        'vue' => [
            'language' => 'javascript', 'name' => 'Vue 3 (Vite)', 'fence' => 'vue', 'scaffold' => 'vue',
            'files' => ['src/views/{Name}View.vue'],
            'notes' => 'A single-file component with <script setup>, <template> and <style scoped>. Keep data in refs/arrays and render lists with v-for; use <RouterLink> to move between screens.',
        ],
        'react-ts' => [
            'language' => 'typescript', 'name' => 'React + TypeScript (Vite)', 'fence' => 'tsx', 'scaffold' => 'react-ts',
            'files' => ['src/pages/{Name}.tsx', 'src/pages/{Name}.css'],
            'notes' => 'A typed function component `export default function {Name}(): JSX.Element` importing "./{Name}.css". Declare types for list items and use Link from react-router-dom.',
        ],
        'nextjs' => [
            'language' => 'typescript', 'name' => 'Next.js (App Router)', 'fence' => 'tsx', 'scaffold' => 'nextjs',
            'files' => ['app/{slug}/page.tsx', 'app/{slug}/page.module.css'],
            'notes' => 'A server component `export default function {Name}Page()` that imports styles from "./page.module.css" (CSS Modules, camelCase class names). Use next/link for navigation.',
        ],
        'angular' => [
            'language' => 'typescript', 'name' => 'Angular (standalone)', 'fence' => 'typescript', 'scaffold' => 'angular',
            'files' => ['src/app/{slug}/{slug}.component.ts', 'src/app/{slug}/{slug}.component.html', 'src/app/{slug}/{slug}.component.css'],
            'notes' => 'A standalone component class {Name}Component with selector "app-{slug}", templateUrl and styleUrl. Put list data in typed class fields and render with @for; use routerLink.',
        ],
        'blade' => [
            'language' => 'php', 'name' => 'Laravel Blade', 'fence' => 'blade', 'scaffold' => 'blade',
            'files' => ['resources/views/{slug}.blade.php', 'public/css/{slug}.css'],
            'notes' => 'A view that does @extends(\'layouts.app\') and fills @section(\'content\'). Link its CSS with @push(\'styles\') using asset(\'css/{slug}.css\'). Use @foreach over a PHP array declared in @php for repeated items and route() style links to the other screens.',
        ],
        'php' => [
            'language' => 'php', 'name' => 'Plain PHP', 'fence' => 'php',
            'files' => ['{slug}.php', 'assets/css/{slug}.css'],
            'notes' => 'A PHP page with the data as PHP arrays at the top and an HTML template below that loops with foreach and escapes output with htmlspecialchars. Link "assets/css/{slug}.css".',
        ],
        'django' => [
            'language' => 'python', 'name' => 'Django templates', 'fence' => 'django', 'scaffold' => 'django',
            'files' => ['templates/{snake}.html', 'static/css/{snake}.css'],
            'notes' => 'A template that does {% extends "base.html" %}, loads static and fills {% block content %}. Loop lists with {% for %} and link screens with {% url \'{snake}\' %} style names.',
        ],
        'flask' => [
            'language' => 'python', 'name' => 'Flask + Jinja2', 'fence' => 'jinja', 'scaffold' => 'flask',
            'files' => ['templates/{snake}.html', 'static/css/{snake}.css'],
            'notes' => 'A Jinja2 template that does {% extends "base.html" %} and fills {% block content %}. Link CSS with url_for(\'static\', filename=\'css/{snake}.css\') and other screens with url_for(\'{snake}\').',
        ],
        'flutter' => [
            'language' => 'dart', 'name' => 'Flutter', 'fence' => 'dart', 'scaffold' => 'flutter',
            'files' => ['lib/screens/{snake}_screen.dart'],
            'notes' => 'A StatelessWidget class {Name}Screen returning a Scaffold. Use Material 3 widgets, a const color scheme, ListView/GridView builders for repeated items and Navigator.pushNamed(context, \'/{slug}\') between screens.',
        ],
        'compose' => [
            'language' => 'kotlin', 'name' => 'Jetpack Compose', 'fence' => 'kotlin', 'scaffold' => 'compose',
            'files' => ['app/src/main/java/com/example/app/ui/screens/{Name}Screen.kt'],
            'notes' => 'A @Composable fun {Name}Screen(onNavigate: (String) -> Unit = {}) using Material3 Scaffold, LazyColumn/LazyRow for lists and a @Preview function.',
        ],
        'swiftui' => [
            'language' => 'swift', 'name' => 'SwiftUI', 'fence' => 'swift', 'scaffold' => 'swiftui',
            'files' => ['App/Views/{Name}View.swift'],
            'notes' => 'A struct {Name}View: View using NavigationStack-friendly layouts, ForEach over Identifiable model structs and a #Preview block.',
        ],
    ];

    /** Profile for frameworks a user typed in themselves. */
    public static function generic(string $name, string $languageName): array
    {
        return [
            'name' => $name, 'fence' => '', 'files' => [],
            'notes' => "Write idiomatic {$name} code in {$languageName}. Choose the conventional file names and folders a {$name} project uses for one screen/page and its styles.",
        ];
    }

    /** Fills {Name}, {slug} and {snake} in a file pattern. */
    public static function path(string $pattern, string $slug): string
    {
        $words = preg_split('/[^a-z0-9]+/i', $slug, -1, PREG_SPLIT_NO_EMPTY) ?: ['screen'];
        $pascal = implode('', array_map(fn ($w) => ucfirst(strtolower($w)), $words));
        if (preg_match('/^\d/', $pascal)) {
            $pascal = 'Screen'.$pascal;
        }

        return strtr($pattern, [
            '{Name}' => $pascal,
            '{slug}' => strtolower(implode('-', $words)),
            '{snake}' => strtolower(implode('_', $words)),
        ]);
    }

    public static function pascal(string $slug): string
    {
        return basename(self::path('{Name}', $slug));
    }
}
