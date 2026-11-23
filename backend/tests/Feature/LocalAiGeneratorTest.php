<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocalAiGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.local_ai.base_url' => 'http://127.0.0.1:1235/v1',
            'services.local_ai.model' => 'qwen2.5-coder-7b-instruct',
            'services.local_ai.timeout' => 10,
            'services.anthropic.key' => null,
        ]);
    }

    public function test_generator_uses_local_ai_when_configured(): void
    {
        Http::fake([
            'http://127.0.0.1:1235/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => "```html\n<!DOCTYPE html><html><head><title>Test Mockup</title></head><body><h1>Doctor Appointment</h1></body></html>\n```",
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $res = $this->actingAs($user, 'sanctum')->postJson('/api/generations', [
            'prompt' => 'doctor appointment app',
            'platform' => 'mobile',
            'screen_type' => 'home',
            'style' => 'modern',
            'use_ai' => true,
        ])->assertCreated();

        $this->assertSame('local', $res->json('data.provider'));
        $this->assertStringContainsString('<!DOCTYPE html>', $res->json('data.html'));
        $this->assertStringContainsString('Doctor Appointment', $res->json('data.html'));
        $this->assertStringNotContainsString('```', $res->json('data.html'));
    }

    public function test_generator_falls_back_to_template_when_local_ai_fails(): void
    {
        Http::fake([
            'http://127.0.0.1:1235/v1/chat/completions' => Http::response(['error' => 'server error'], 500),
        ]);

        $user = User::factory()->create();

        $res = $this->actingAs($user, 'sanctum')->postJson('/api/generations', [
            'prompt' => 'doctor appointment app',
            'platform' => 'mobile',
            'screen_type' => 'home',
            'style' => 'modern',
            'use_ai' => true,
        ])->assertCreated();

        $this->assertSame('template', $res->json('data.provider'));
        $this->assertNotNull($res->json('notice'));
    }

    public function test_generator_options_reports_ai_enabled_when_local_ai_configured(): void
    {
        $res = $this->getJson('/api/generator/options')->assertOk();
        $this->assertTrue($res->json('ai_enabled'));
    }
}
