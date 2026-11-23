<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Design;
use App\Models\Like;
use App\Models\Tag;
use App\Models\User;
use App\Services\PaletteExtractor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public const CATEGORIES = [
        ['E-commerce', '🛍️', 'Online stores, marketplaces and shopping apps'],
        ['Food & Delivery', '🍔', 'Restaurants, food delivery and grocery'],
        ['Health & Medical', '🩺', 'Clinics, telemedicine, pharmacy and health tracking'],
        ['Finance & Banking', '💳', 'Banking, wallets, payments and investing'],
        ['Education', '🎓', 'Courses, LMS, schools and tutoring'],
        ['Travel & Booking', '✈️', 'Hotels, flights, tours and trip planning'],
        ['Real Estate', '🏠', 'Rentals, property listings and agents'],
        ['Fitness & Wellness', '🏋️', 'Workouts, yoga, diet and wellbeing'],
        ['Agriculture', '🌾', 'Smart farming, crop markets and agritech'],
        ['Social & Community', '💬', 'Social networks, chat and communities'],
        ['Productivity & SaaS', '🗂️', 'Project management, CRM, HR and tools'],
        ['Home & Local Services', '🧺', 'Laundry, cleaning, repairs and on-demand services'],
        ['Jobs & Careers', '💼', 'Job portals, hiring and resumes'],
        ['Entertainment & Media', '🎬', 'Streaming, music, games and events'],
        ['News & Blog', '📰', 'Publishing, magazines and blogs'],
        ['Portfolio & Personal', '🎨', 'Portfolios, resumes and personal sites'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $i => [$name, $icon, $desc]) {
            Category::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'icon' => $icon, 'description' => $desc, 'sort_order' => $i]);
        }

        $admin = User::firstOrCreate(['email' => 'admin@uispark.test'], ['name' => 'UISpark Admin', 'password' => 'password']);
        $admin->forceFill(['role' => 'admin'])->save();

        $demo = User::firstOrCreate(['email' => 'demo@uispark.test'], ['name' => 'Demo Designer', 'password' => 'password', 'bio' => 'Collecting UI ideas one screen at a time.']);

        if (Design::exists()) {
            return; // don't duplicate demo content on re-seed
        }

        $this->seedDemoDesigns([$admin, $demo]);
    }

    private function seedDemoDesigns(array $authors): void
    {
        $dir = database_path('seeders/demo');
        $manifest = json_decode(file_get_contents("{$dir}/manifest.json"), true);
        $palette = new PaletteExtractor;
        // Map seeder category keys to real category slugs
        $catSlug = fn ($key) => Category::where('slug', $key)->value('id')
            ?? Category::where('slug', 'like', "{$key}%")->value('id');

        $designs = [];
        foreach ($manifest as $i => $row) {
            $source = "{$dir}/{$row['slug']}.jpg";
            if (! is_file($source)) {
                continue;
            }
            $path = "designs/demo/{$row['slug']}.jpg";
            Storage::disk('public')->put($path, file_get_contents($source));
            [$w, $h] = getimagesize($source);
            $colors = $palette->extract($source);

            $design = Design::create([
                'user_id' => $authors[$i % 2]->id,
                'category_id' => $catSlug($row['cat']),
                'title' => $row['title'],
                'description' => ucfirst($row['prompt']).' — a '.str_replace('-', ' ', $row['s']).' screen for '.($row['p'] === 'web' ? 'the web' : 'mobile').'.',
                'platform' => $row['p'],
                'screen_type' => $row['s'],
                'image_path' => $path,
                'image_width' => $w,
                'image_height' => $h,
                'palette' => $colors,
                'color_families' => PaletteExtractor::families($colors),
                'status' => 'approved',
                'approved_at' => now()->subHours(count($manifest) - $i),
                'views_count' => random_int(20, 400),
            ]);
            $design->tags()->sync(Tag::idsFromNames(explode(',', $row['tags'])));
            $designs[] = $design;
        }

        // A few likes and comments so the gallery feels alive.
        foreach ($designs as $i => $design) {
            foreach ($authors as $j => $author) {
                if (($i + $j) % 3 !== 0) {
                    Like::create(['user_id' => $author->id, 'design_id' => $design->id]);
                    $design->increment('likes_count');
                }
            }
        }
        foreach (array_slice($designs, 0, 6) as $design) {
            $c = Comment::create(['user_id' => $authors[1]->id, 'design_id' => $design->id, 'body' => 'Love the spacing and the clear hierarchy on this one.']);
            Comment::create(['user_id' => $authors[0]->id, 'design_id' => $design->id, 'parent_id' => $c->id, 'body' => 'Agreed — the card layout would work well for our project too.']);
            $design->increment('comments_count', 2);
        }
    }
}
