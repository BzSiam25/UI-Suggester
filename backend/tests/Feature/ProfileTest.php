<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Design;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_accounts_get_a_unique_username(): void
    {
        $a = User::factory()->create(['name' => 'Bayezid Hasan']);
        $b = User::factory()->create(['name' => 'Bayezid Hasan']);

        $this->assertSame('bayezidhasan', $a->username);
        $this->assertSame('bayezidhasan2', $b->username);

        $res = $this->postJson('/api/auth/register', [
            'name' => 'Siam', 'email' => 'siam@example.com', 'password' => 'secret#123', 'password_confirmation' => 'secret#123',
        ])->assertCreated();
        $this->assertSame('siam', $res->json('user.username'));
    }

    public function test_owner_updates_profile_fields(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user, 'sanctum')->putJson('/api/auth/profile', [
            'name' => 'Bayezid Hasan Siam',
            'username' => 'siam_dev',
            'headline' => 'Software Engineer · Laravel + React',
            'bio' => 'I build things.',
            'location' => 'Dhaka, Bangladesh',
            'website' => 'https://example.com',
            'skills' => ['Laravel', 'React', ' React ', ''],
            'links' => ['github' => 'https://github.com/someone', 'linkedin' => '', 'myspace' => 'https://myspace.com/x'],
            'is_profile_public' => true,
        ])->assertOk();

        $res->assertJsonPath('data.username', 'siam_dev')
            ->assertJsonPath('data.headline', 'Software Engineer · Laravel + React')
            ->assertJsonPath('data.skills', ['Laravel', 'React'])
            ->assertJsonPath('data.links.github', 'https://github.com/someone')
            ->assertJsonMissingPath('data.links.myspace')
            ->assertJsonMissingPath('data.links.linkedin');
    }

    public function test_username_rules(): void
    {
        $taken = User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create();

        foreach (['taken', 'Has Space', 'ab', 'admin'] as $bad) {
            $this->actingAs($user, 'sanctum')->putJson('/api/auth/profile', ['username' => $bad])
                ->assertStatus(422)->assertJsonValidationErrors('username');
        }
        $this->actingAs($taken, 'sanctum')->putJson('/api/auth/profile', ['username' => 'taken'])->assertOk();
    }

    public function test_public_profile_shows_stats_and_work(): void
    {
        $user = User::factory()->create(['username' => 'maker', 'headline' => 'Designer']);
        $category = Category::create(['name' => 'Health', 'slug' => 'health']);
        foreach ([['approved', 5], ['approved', 3], ['pending', 9]] as $i => [$status, $likes]) {
            $design = Design::create([
                'user_id' => $user->id, 'category_id' => $category->id, 'title' => "Design {$i}", 'slug' => "design-{$i}",
                'platform' => 'web', 'screen_type' => 'home', 'image_path' => "designs/{$i}.png",
                'status' => $status, 'approved_at' => $status === 'approved' ? now() : null,
            ]);
            Design::whereKey($design->id)->update(['likes_count' => $likes]);
        }
        $user->generations()->create([
            'prompt' => 'Clinic booking app', 'platform' => 'web', 'style' => 'modern', 'provider' => 'local',
            'status' => 'done', 'is_public' => true, 'screens' => [['slug' => 'home', 'name' => 'Home']],
        ]);
        $user->generations()->create([
            'prompt' => 'Private draft', 'platform' => 'web', 'style' => 'modern', 'provider' => 'local', 'status' => 'done',
        ]);

        $this->getJson('/api/users/maker')->assertOk()
            ->assertJsonPath('data.headline', 'Designer')
            ->assertJsonPath('stats.designs', 2)
            ->assertJsonPath('stats.likes_received', 8)
            ->assertJsonPath('stats.projects', 1)
            ->assertJsonPath('is_owner', false)
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('stats.pending_designs');

        $this->getJson('/api/users/maker/designs')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.user.username', 'maker');
        $this->getJson('/api/users/maker/projects')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.html');

        $this->actingAs($user, 'sanctum')->getJson('/api/users/maker')
            ->assertJsonPath('is_owner', true)->assertJsonPath('stats.pending_designs', 1)->assertJsonPath('stats.all_projects', 2);
    }

    public function test_private_profiles_are_hidden_from_others(): void
    {
        $user = User::factory()->create(['username' => 'quiet', 'is_profile_public' => false]);
        $other = User::factory()->create();

        $this->getJson('/api/users/quiet')->assertNotFound();
        $this->actingAs($other, 'sanctum')->getJson('/api/users/quiet')->assertNotFound();
        $this->actingAs($user, 'sanctum')->getJson('/api/users/quiet')->assertOk();
        $this->getJson('/api/users/nobody')->assertNotFound();
    }

    public function test_avatar_upload_is_cropped_square_and_replaces_the_old_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $first = $this->actingAs($user, 'sanctum')->post('/api/auth/avatar', [
            'avatar' => UploadedFile::fake()->image('me.png', 800, 500),
        ], ['Accept' => 'application/json'])->assertOk();
        $path = $user->fresh()->avatar_path;
        Storage::disk('public')->assertExists($path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([320, 320], [$w, $h]);
        $this->assertStringContainsString($path, $first->json('data.avatar_url'));

        $this->actingAs($user, 'sanctum')->post('/api/auth/avatar', [
            'avatar' => UploadedFile::fake()->image('me2.jpg', 100, 100),
        ], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertMissing($path);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/auth/avatar')->assertOk()->assertJsonPath('data.avatar_url', null);
        $this->actingAs($user, 'sanctum')->post('/api/auth/avatar', ['avatar' => UploadedFile::fake()->create('x.pdf', 10)], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_password_change_needs_the_current_password_and_a_symbol(): void
    {
        $user = User::factory()->create(['password' => 'old#pass123']);

        $this->actingAs($user, 'sanctum')->putJson('/api/auth/password', [
            'current_password' => 'wrong', 'password' => 'new#pass123', 'password_confirmation' => 'new#pass123',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->actingAs($user, 'sanctum')->putJson('/api/auth/password', [
            'current_password' => 'old#pass123', 'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->actingAs($user, 'sanctum')->putJson('/api/auth/password', [
            'current_password' => 'old#pass123', 'password' => 'new#pass123', 'password_confirmation' => 'new#pass123',
        ])->assertOk();

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'new#pass123'])->assertOk();
    }
}
