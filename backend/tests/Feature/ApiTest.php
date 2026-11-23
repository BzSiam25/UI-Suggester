<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Design;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config([
            'services.anthropic.key' => null,
            'services.local_ai.base_url' => null,
        ]); // always use the template engine in tests
        $this->category = Category::create(['name' => 'E-commerce', 'slug' => 'e-commerce']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function upload(User $user, array $overrides = [])
    {
        return $this->actingAs($user, 'sanctum')->post('/api/designs', array_merge([
            'title' => 'Checkout screen',
            'category_id' => $this->category->id,
            'platform' => 'mobile',
            'screen_type' => 'checkout',
            'tags' => ['clean', 'Dark mode'],
            'image' => UploadedFile::fake()->image('shot.png', 390, 844),
        ], $overrides), ['Accept' => 'application/json']);
    }

    public function test_register_and_login_return_tokens(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Siam', 'email' => 'siam@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertCreated()->assertJsonStructure(['user' => ['id', 'name'], 'token']);

        $this->postJson('/api/auth/login', ['email' => 'siam@example.com', 'password' => 'secret123'])
            ->assertOk()->assertJsonStructure(['token']);

        $this->postJson('/api/auth/login', ['email' => 'siam@example.com', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_community_upload_is_pending_until_admin_approves(): void
    {
        $user = User::factory()->create();
        $res = $this->upload($user)->assertCreated();
        $slug = $res->json('data.slug');

        $this->assertSame('pending', $res->json('data.status'));
        $this->assertNotEmpty($res->json('data.palette'));
        $this->assertCount(2, $res->json('data.tags'));

        // Hidden from the public gallery and from strangers
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/designs')->assertJsonCount(0, 'data');
        $this->getJson("/api/designs/{$slug}")->assertNotFound();
        // ...but visible to its owner
        $this->actingAs($user, 'sanctum')->getJson("/api/designs/{$slug}")->assertOk();

        // Regular users cannot moderate
        $this->actingAs($user, 'sanctum')->patchJson("/api/admin/designs/{$slug}/approve")->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/designs/{$slug}/approve")->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->getJson('/api/designs')->assertJsonCount(1, 'data');
        $this->getJson('/api/designs?platform=web')->assertJsonCount(0, 'data');
        $this->getJson('/api/designs?category=e-commerce&screen_type=checkout')->assertJsonCount(1, 'data');
        $this->getJson('/api/designs?q=checkout')->assertJsonCount(1, 'data');
    }

    public function test_admin_uploads_are_published_immediately(): void
    {
        $this->upload($this->admin())->assertCreated()->assertJsonPath('data.status', 'approved');
    }

    public function test_reject_requires_reason_and_owner_edit_requeues(): void
    {
        $user = User::factory()->create();
        $slug = $this->upload($user)->json('data.slug');
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/designs/{$slug}/reject")->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/designs/{$slug}/reject", ['reason' => 'Blurry image'])
            ->assertJsonPath('data.status', 'rejected');

        $this->actingAs($user, 'sanctum')->putJson("/api/designs/{$slug}", ['title' => 'Checkout screen v2'])
            ->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_like_bookmark_and_comment_flow(): void
    {
        $owner = User::factory()->create();
        $design = Design::find($this->upload($this->admin())->json('data.id'));
        $fan = User::factory()->create();

        $this->actingAs($fan, 'sanctum')->postJson("/api/designs/{$design->slug}/like")->assertJson(['liked' => true, 'likes_count' => 1]);
        $this->actingAs($fan, 'sanctum')->postJson("/api/designs/{$design->slug}/like")->assertJson(['liked' => false, 'likes_count' => 0]);

        $collection = $this->actingAs($fan, 'sanctum')->postJson('/api/my/collections', ['name' => 'Checkout ideas'])->json('data.id');
        $this->actingAs($fan, 'sanctum')->postJson("/api/designs/{$design->slug}/bookmark", ['collection_id' => $collection])
            ->assertJson(['bookmarked' => true]);
        $this->actingAs($fan, 'sanctum')->getJson("/api/my/bookmarks?collection_id={$collection}")->assertJsonCount(1, 'data');

        $parent = $this->actingAs($fan, 'sanctum')->postJson("/api/designs/{$design->slug}/comments", ['body' => 'Nice!'])
            ->assertCreated()->json('data.id');
        $this->actingAs($owner, 'sanctum')->postJson("/api/designs/{$design->slug}/comments", ['body' => 'Thanks', 'parent_id' => $parent])
            ->assertCreated();
        $this->assertSame(2, $design->fresh()->comments_count);

        $this->getJson("/api/designs/{$design->slug}/comments")->assertJsonCount(1, 'data')->assertJsonCount(1, 'data.0.replies');

        // Someone else can't delete it; the author can, and replies go with it.
        $this->actingAs($owner, 'sanctum')->deleteJson("/api/comments/{$parent}")->assertForbidden();
        $this->actingAs($fan, 'sanctum')->deleteJson("/api/comments/{$parent}")->assertOk();
        $this->assertSame(0, $design->fresh()->comments_count);
    }

    public function test_generator_uses_template_engine_without_api_key(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user, 'sanctum')->postJson('/api/generations', [
            'prompt' => 'pharmacy delivery app',
            'platform' => 'mobile',
            'screen_type' => 'home',
            'style' => 'modern',
            'accent' => '#0d9488',
        ])->assertCreated();

        $this->assertSame('template', $res->json('data.provider'));
        $this->assertStringContainsString('<!DOCTYPE html>', $res->json('data.html'));
        $this->assertStringContainsString('#0d9488', $res->json('data.html'));

        $id = $res->json('data.id');
        // Private by default
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/generations/{$id}")->assertNotFound();
        $this->actingAs($user, 'sanctum')->patchJson("/api/generations/{$id}", ['is_public' => true])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/generations/public')->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.html');
    }

    public function test_banned_users_are_blocked(): void
    {
        $user = User::factory()->create();
        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/users/{$user->id}", ['is_banned' => true])->assertOk();
        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/auth/me')->assertForbidden();
    }
}
