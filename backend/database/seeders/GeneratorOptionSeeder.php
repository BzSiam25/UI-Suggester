<?php

namespace Database\Seeders;

use App\Models\GeneratorOption;
use App\Services\Generator\FrameworkCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Built-in generator choices. Safe to run again: rows are matched by type and slug. */
class GeneratorOptionSeeder extends Seeder
{
    public const SCREENS = [
        ['landing', 'Landing page'], ['home', 'Home'], ['login', 'Login'], ['signup', 'Sign up'],
        ['forgot-password', 'Forgot password'], ['onboarding', 'Onboarding'], ['dashboard', 'Dashboard'],
        ['product-list', 'Product list'], ['product-detail', 'Product detail'], ['cart', 'Cart'],
        ['checkout', 'Checkout'], ['profile', 'Profile'], ['settings', 'Settings'], ['search', 'Search'],
        ['chat', 'Chat'], ['notifications', 'Notifications'], ['pricing', 'Pricing'], ['blog', 'Blog'],
        ['about', 'About'], ['contact', 'Contact'], ['admin-panel', 'Admin panel'], ['empty-state', 'Empty state'],
    ];

    public function run(): void
    {
        foreach (FrameworkCatalog::LANGUAGES as $i => $lang) {
            $this->put('language', $lang['slug'], $lang['name'], $i, ['description' => $lang['description']]);
        }

        $i = 0;
        foreach (FrameworkCatalog::FRAMEWORKS as $slug => $fw) {
            $meta = collect($fw)->except(['language', 'name'])->all();
            $this->put('framework', $slug, $fw['name'], $i++, ['parent' => $fw['language'], 'meta' => $meta]);
        }

        foreach (self::SCREENS as $i => [$slug, $name]) {
            $this->put('screen', $slug, $name, $i);
        }

        foreach (DatabaseSeeder::CATEGORIES as $i => [$name, $icon, $desc]) {
            $this->put('domain', Str::slug($name), $name, $i, ['description' => $desc]);
        }
    }

    private function put(string $type, string $slug, string $name, int $order, array $extra = []): void
    {
        GeneratorOption::updateOrCreate(
            ['type' => $type, 'slug' => $slug, 'user_id' => null],
            ['name' => $name, 'sort_order' => $order] + $extra,
        );
    }
}
