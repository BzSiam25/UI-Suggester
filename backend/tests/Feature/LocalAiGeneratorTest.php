<?php

namespace Tests\Feature;

use App\Jobs\GenerateUiProject;
use App\Models\GeneratorOption;
use App\Models\UiGeneration;
use App\Models\User;
use App\Services\Generator\ProjectGenerator;
use Database\Seeders\GeneratorOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LocalAiGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'http://127.0.0.1:1235/v1';

    /** What the fake Qwen answers; tests swap it. Laravel keeps the first fake, so it is registered once. */
    private $answer = null;

    private bool $online = true;

    private int $calls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.local_ai.base_url' => self::BASE,
            'services.local_ai.model' => 'qwen2.5-coder-7b-instruct',
            'services.local_ai.timeout' => 10,
            'services.inspiration.enabled' => false,
            'queue.default' => 'sync',
        ]);
        $this->seed(GeneratorOptionSeeder::class);

        Http::fake(function (HttpRequest $request) {
            if (! $this->online) {
                throw new ConnectionException('Connection refused');
            }
            if (str_ends_with($request->url(), '/models')) {
                return Http::response(['data' => [['id' => 'qwen2.5-coder-7b-instruct']]]);
            }
            $this->calls++;
            $system = $request['messages'][0]['content'];
            $user = $request['messages'][1]['content'];
            $content = $this->answer ? ($this->answer)($system, $user) : $this->defaultAnswer($system, $user);

            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]]);
        });
    }

    private function fakeQwen(?callable $chat = null): void
    {
        $this->answer = $chat;
    }

    private function defaultAnswer(string $system, string $user): string
    {
        if (str_contains($system, 'JSON only')) {
            return '```json
{"brand": "MediSlot", "tagline": "Book a doctor in a minute", "primary": "#0d9488", "secondary": "#f59e0b", "font": "Inter",
 "screens": {"login": "Email and password form with a forgot link", "dashboard": "Upcoming appointments and doctor list"}}
```';
        }
        if (str_contains($system, 'UI designer')) {
            preg_match('/Design the "([^"]+)" screen/', $user, $m);

            return "Here it is:\n```html\n<!DOCTYPE html><html><head><title>{$m[1]}</title><style>h1 { color: teal; }</style></head><body><h1>MediSlot {$m[1]}</h1></body></html>\n```";
        }
        preg_match('/^- (src\/pages\/\w+\.jsx)$/m', $user, $jsx);
        preg_match('/^- (src\/pages\/\w+\.css)$/m', $user, $css);
        if (str_contains($user, 'Dashboard.jsx')) {
            return "### FILE: {$jsx[1]}\n```jsx\nimport './Dashboard.css'\nexport default function Page() {\n  return <h1>Dashboard</h1>\n}\n```";
        }

        return "### FILE: {$jsx[1]}\n```jsx\nexport default function Page() {\n  return <label class=\"title\" for=\"phone\">MediSlot</label>\n}\n```\n\n### FILE: {$css[1]}\n```css\nh1 { color: #0d9488; }\n```";
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'prompt' => 'Doctor appointment booking app for clinics in Dhaka',
            'language' => 'javascript',
            'framework' => 'react',
            'platform' => 'web',
            'style' => 'modern',
            'screens' => ['login', 'dashboard'],
            'domains' => ['health-medical'],
            'use_research' => false,
        ], $overrides);
    }

    public function test_options_come_from_the_database_with_qwen_status(): void
    {
        $this->fakeQwen();

        $res = $this->getJson('/api/generator/options')->assertOk();

        $this->assertTrue($res->json('qwen.online'));
        $this->assertContains('react', collect($res->json('frameworks'))->pluck('slug'));
        $this->assertSame('javascript', collect($res->json('frameworks'))->firstWhere('slug', 'react')['language']);
        $this->assertContains('login', collect($res->json('screens'))->pluck('slug'));
        $this->assertContains('health-medical', collect($res->json('domains'))->pluck('slug'));
        $this->assertContains('dart', collect($res->json('languages'))->pluck('slug'));
    }

    public function test_users_can_add_their_own_screens_and_domains(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/generator/options', ['type' => 'screen', 'name' => 'Prescription upload'])
            ->assertCreated()->assertJsonPath('data.slug', 'prescription-upload')->assertJsonPath('data.custom', true);
        $this->actingAs($user, 'sanctum')->postJson('/api/generator/options', ['type' => 'domain', 'name' => 'Veterinary'])->assertCreated();
        // Adding a built-in name again returns the existing row instead of a duplicate.
        $this->actingAs($user, 'sanctum')->postJson('/api/generator/options', ['type' => 'screen', 'name' => 'Login'])
            ->assertOk()->assertJsonPath('data.custom', false);
        $this->actingAs($user, 'sanctum')->postJson('/api/generator/options', ['type' => 'framework', 'name' => 'SolidJS'])
            ->assertStatus(422);

        $this->fakeQwen();
        $mine = $this->actingAs($user, 'sanctum')->getJson('/api/generator/options');
        $this->assertContains('prescription-upload', collect($mine->json('screens'))->pluck('slug'));

        $theirs = $this->actingAs($other, 'sanctum')->getJson('/api/generator/options');
        $this->assertNotContains('prescription-upload', collect($theirs->json('screens'))->pluck('slug'));
    }

    public function test_generation_is_queued_and_qwen_writes_previews_and_framework_code(): void
    {
        $this->fakeQwen();
        $user = User::factory()->create();

        $res = $this->actingAs($user, 'sanctum')->postJson('/api/generations', $this->payload())->assertStatus(202);
        $id = $res->json('data.id');

        $show = $this->actingAs($user, 'sanctum')->getJson("/api/generations/{$id}")->assertOk();
        $this->assertSame('done', $show->json('data.status'), (string) $show->json('data.error'));
        $this->assertSame('local', $show->json('data.provider'));
        $this->assertSame(['login', 'dashboard'], collect($show->json('data.previews'))->pluck('slug')->all());
        $this->assertStringContainsString('MediSlot Login', $show->json('data.previews.0.html'));
        $this->assertStringNotContainsString('```', $show->json('data.previews.0.html'));

        $paths = collect($show->json('data.files'))->pluck('path');
        foreach (['src/pages/Login.jsx', 'src/pages/Login.css', 'src/pages/Dashboard.jsx', 'src/App.jsx', 'package.json', 'README.md', 'design-preview/login.html'] as $path) {
            $this->assertContains($path, $paths, "missing {$path}");
        }
        $login = collect($show->json('data.files'))->firstWhere('path', 'src/pages/Login.jsx')['content'];
        $this->assertStringContainsString('<label className="title" htmlFor="phone">', $login);
        $app = collect($show->json('data.files'))->firstWhere('path', 'src/App.jsx')['content'];
        $this->assertStringContainsString("import Dashboard from './pages/Dashboard'", $app);
        $this->assertSame('MediSlot', $show->json('data.design.brand'));
        // The model skipped Dashboard.css: it is rebuilt from the preview's <style>.
        $css = collect($show->json('data.files'))->firstWhere('path', 'src/pages/Dashboard.css');
        $this->assertNotNull($css);
        $this->assertStringContainsString('color: teal', $css['content']);
        $this->assertSame('Health & Medical', $show->json('data.domains.0.name'));

        $this->actingAs($user, 'sanctum')->get("/api/generations/{$id}/raw?screen=dashboard")->assertOk()->assertSee('MediSlot Dashboard', false);
    }

    public function test_html_frameworks_use_the_preview_as_the_code(): void
    {
        $this->fakeQwen();
        $user = User::factory()->create();

        $id = $this->actingAs($user, 'sanctum')->postJson('/api/generations', $this->payload([
            'language' => 'html-css-js', 'framework' => 'tailwind', 'screens' => ['login'],
        ]))->assertStatus(202)->json('data.id');

        $files = collect(UiGeneration::find($id)->files)->pluck('path');
        $this->assertContains('login.html', $files);
        $this->assertSame(2, $this->calls); // plan + one preview, no separate code pass
    }

    public function test_framework_must_belong_to_the_language(): void
    {
        $this->fakeQwen();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/generations', $this->payload(['language' => 'python', 'framework' => 'react']))
            ->assertStatus(422)->assertJsonValidationErrors('framework');
        $this->actingAs($user, 'sanctum')->postJson('/api/generations', $this->payload(['screens' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('screens');
    }

    public function test_custom_language_and_framework_get_generic_instructions(): void
    {
        $this->fakeQwen(function ($system, $user) {
            if (str_contains($system, 'Svelte')) {
                return "### FILE: src/routes/login/+page.svelte\n```svelte\n<h1>MediSlot</h1>\n```";
            }

            return $this->defaultAnswer($system, $user);
        });
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/generator/options', ['type' => 'framework', 'name' => 'Svelte', 'parent' => 'javascript'])->assertCreated();

        $id = $this->actingAs($user, 'sanctum')->postJson('/api/generations', $this->payload(['framework' => 'svelte', 'screens' => ['login']]))
            ->assertStatus(202)->json('data.id');

        $generation = UiGeneration::find($id);
        $this->assertSame('done', $generation->status, (string) $generation->error);
        $this->assertContains('src/routes/login/+page.svelte', collect($generation->files)->pluck('path'));
    }

    public function test_generation_is_refused_when_qwen_is_not_running(): void
    {
        $this->online = false;
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/generations', $this->payload())
            ->assertStatus(503)->assertJsonPath('qwen.online', false);
        $this->assertSame(0, UiGeneration::count());
    }

    public function test_a_failing_model_marks_the_generation_failed_and_it_can_be_retried(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->fakeQwen();
        $id = $this->actingAs($user, 'sanctum')->postJson('/api/generations', $this->payload(['screens' => ['login']]))->json('data.id');
        Queue::assertPushed(GenerateUiProject::class);
        $this->assertSame('pending', UiGeneration::find($id)->status);

        $this->fakeQwen(fn ($system) => str_contains($system, 'JSON only') ? '{}' : 'Sorry, I cannot help with that.');
        (new GenerateUiProject($id))->handle(app(ProjectGenerator::class));
        $failed = UiGeneration::find($id);
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('did not return an HTML page', $failed->error);

        $this->actingAs($user, 'sanctum')->postJson("/api/generations/{$id}/retry")->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_only_local_model_servers_are_accepted(): void
    {
        config(['services.local_ai.base_url' => 'https://api.example.com/v1']);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/generations', $this->payload())->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_file_parser_handles_unlabelled_and_cut_off_answers(): void
    {
        $gen = app(ProjectGenerator::class);

        $files = $gen->parseFiles("```jsx\nexport default function A() {}\n```\n```css\n.a{}\n```", ['src/pages/A.jsx', 'src/pages/A.css'], 'jsx');
        $this->assertSame(['src/pages/A.jsx', 'src/pages/A.css'], array_column($files, 'path'));

        $files = $gen->parseFiles("### FILE: lib/screens/home_screen.dart\n```dart\nclass Home {", ['lib/screens/home_screen.dart'], 'dart');
        $this->assertSame('dart', $files[0]['language']);
        $this->assertStringContainsString('class Home', $files[0]['content']);

        $this->assertSame([], $gen->parseFiles("### FILE: ../../etc/passwd\n```\nx\n```", [], ''));
    }

    public function test_runaway_base64_images_are_removed_from_previews(): void
    {
        $gen = app(ProjectGenerator::class);
        $blob = str_repeat('PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcv', 40);

        $closed = $gen->extractHtml('<!DOCTYPE html><html><body><h1>Hi</h1><img alt="Dr" src="data:image/svg+xml;base64,'.$blob.'"><p>After</p></body></html>');
        $this->assertStringNotContainsString('base64', $closed);
        $this->assertStringContainsString('<p>After</p>', $closed);

        $cut = $gen->extractHtml('<!DOCTYPE html><html><body><h1>Hi</h1><div><img src="data:image/png;base64,'.$blob);
        $this->assertStringNotContainsString('base64', $cut);
        $this->assertStringContainsString('<h1>Hi</h1>', $cut);
        $this->assertStringEndsWith('</html>', $cut);
    }

    public function test_deleting_custom_options_is_limited_to_the_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $id = $this->actingAs($owner, 'sanctum')->postJson('/api/generator/options', ['type' => 'screen', 'name' => 'Wallet'])->json('data.id');

        $this->actingAs($other, 'sanctum')->deleteJson("/api/generator/options/{$id}")->assertForbidden();
        $builtIn = GeneratorOption::whereNull('user_id')->first();
        $this->actingAs($owner, 'sanctum')->deleteJson("/api/generator/options/{$builtIn->id}")->assertForbidden();
        $this->actingAs($owner, 'sanctum')->deleteJson("/api/generator/options/{$id}")->assertOk();
    }
}
