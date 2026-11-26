<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GenerationResource;
use App\Jobs\GenerateUiProject;
use App\Models\Design;
use App\Models\GeneratorOption;
use App\Models\UiGeneration;
use App\Services\Generator\QwenClient;
use App\Services\Generator\TemplateEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GenerationController extends Controller
{
    private const LIST_COLUMNS = [
        'id', 'user_id', 'category_id', 'prompt', 'platform', 'screen_type', 'style', 'accent', 'language', 'framework',
        'screens', 'domains', 'provider', 'status', 'meta', 'is_public', 'created_at',
    ];

    public const MAX_SCREENS = 8;

    public const MAX_DOMAINS = 4;

    /** Everything the generator form offers, read from the database, plus the local model's status. */
    public function options(Request $request, QwenClient $qwen): JsonResponse
    {
        $user = $request->user('sanctum');
        $rows = GeneratorOption::visibleTo($user)->orderBy('sort_order')->orderBy('name')->get();
        $pick = fn (string $type) => $rows->where('type', $type)->values()->map(fn (GeneratorOption $o) => [
            'id' => $o->id,
            'slug' => $o->slug,
            'name' => $o->name,
            'description' => $o->description,
            'custom' => $o->isCustom(),
        ] + ($type === 'framework' ? [
            'language' => $o->parent,
            'preview_is_code' => (bool) ($o->meta['preview_is_code'] ?? false),
        ] : []));

        return response()->json([
            'languages' => $pick('language'),
            'frameworks' => $pick('framework'),
            'screens' => $pick('screen'),
            'domains' => $pick('domain'),
            'platforms' => Design::PLATFORMS,
            'styles' => array_keys(TemplateEngine::STYLES),
            'limits' => ['screens' => self::MAX_SCREENS, 'domains' => self::MAX_DOMAINS, 'prompt' => 4000],
            'research' => (bool) config('services.inspiration.enabled', true),
            'qwen' => Cache::remember('qwen:health', 10, fn () => $qwen->health()),
        ]);
    }

    /** A screen, domain, language or framework the user typed in, saved for next time. */
    public function storeOption(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(GeneratorOption::TYPES)],
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'parent' => ['nullable', 'string', 'max:90'],
        ]);
        $user = $request->user();
        $slug = Str::slug($data['name']) ?: Str::lower(Str::random(6));

        if ($data['type'] === 'framework' && empty($data['parent'])) {
            throw ValidationException::withMessages(['parent' => 'Pick the language this framework is for.']);
        }

        $existing = GeneratorOption::visibleTo($user)->where('type', $data['type'])->where('slug', $slug)->first();
        if ($existing) {
            return response()->json(['data' => $this->optionPayload($existing)]);
        }

        if (GeneratorOption::where('user_id', $user->id)->where('type', $data['type'])->count() >= 50) {
            throw ValidationException::withMessages(['name' => 'You have added the maximum of 50 custom entries of this kind.']);
        }

        $option = GeneratorOption::create([
            'type' => $data['type'],
            'name' => trim($data['name']),
            'slug' => $slug,
            'parent' => $data['type'] === 'framework' ? $data['parent'] : null,
            'user_id' => $user->id,
            'sort_order' => 1000,
        ]);

        return response()->json(['data' => $this->optionPayload($option)], 201);
    }

    public function destroyOption(Request $request, GeneratorOption $option): JsonResponse
    {
        abort_unless($option->user_id === $request->user()->id, 403);
        $option->delete();

        return response()->json(['message' => 'Removed.']);
    }

    /** Queues a generation; the browser polls GET /generations/{id} for progress. */
    public function store(Request $request, QwenClient $qwen): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'prompt' => ['required', 'string', 'min:10', 'max:4000'],
            'language' => ['required', 'string', 'max:90'],
            'framework' => ['required', 'string', 'max:90'],
            'platform' => ['required', Rule::in(Design::PLATFORMS)],
            'style' => ['required', Rule::in(array_keys(TemplateEngine::STYLES))],
            'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'screens' => ['required', 'array', 'min:1', 'max:'.self::MAX_SCREENS],
            'screens.*' => ['string', 'max:90', 'distinct'],
            'domains' => ['nullable', 'array', 'max:'.self::MAX_DOMAINS],
            'domains.*' => ['string', 'max:90', 'distinct'],
            'use_research' => ['sometimes', 'boolean'],
        ], [
            'screens.required' => 'Pick at least one screen to design.',
            'prompt.min' => 'Describe the product and its pages in a sentence or two.',
        ]);

        $language = $this->resolve($user, 'language', [$data['language']]);
        $framework = GeneratorOption::visibleTo($user)->where('type', 'framework')->where('slug', $data['framework'])->first();
        if ($language === [] || ! $framework) {
            throw ValidationException::withMessages(['framework' => 'Pick a language and a framework from the list.']);
        }
        if ($framework->parent !== $data['language']) {
            throw ValidationException::withMessages(['framework' => "{$framework->name} is not a {$language[0]['name']} framework."]);
        }

        $screens = $this->resolve($user, 'screen', $data['screens']);
        if (count($screens) !== count($data['screens'])) {
            throw ValidationException::withMessages(['screens' => 'One of the screens is not in the list any more. Pick again.']);
        }
        $domains = $this->resolve($user, 'domain', $data['domains'] ?? []);

        $health = $qwen->health();
        Cache::put('qwen:health', $health, 10);
        if (! $health['online']) {
            return response()->json([
                'message' => 'Qwen is not running. '.$health['error'],
                'qwen' => $health,
            ], 503);
        }

        $generation = $user->generations()->create([
            'prompt' => $data['prompt'],
            'platform' => $data['platform'],
            'style' => $data['style'],
            'accent' => $data['accent'] ?? null,
            'language' => $data['language'],
            'framework' => $data['framework'],
            'screens' => $screens,
            'domains' => $domains,
            'screen_type' => $screens[0]['slug'],
            'use_research' => $request->boolean('use_research', true),
            'provider' => 'local',
            'status' => 'pending',
            'progress' => ['message' => 'Waiting for the generator worker', 'done' => 0, 'total' => 1, 'log' => []],
        ]);

        GenerateUiProject::dispatch($generation->id);

        return (new GenerationResource($generation->refresh()))->response()->setStatusCode(202);
    }

    /** Runs a failed generation again with the same choices. */
    public function retry(Request $request, UiGeneration $generation): GenerationResource
    {
        abort_unless($request->user()->id === $generation->user_id, 403);
        abort_if(in_array($generation->status, UiGeneration::ACTIVE, true), 409, 'This generation is still running.');

        $generation->forceFill([
            'status' => 'pending', 'error' => null, 'previews' => null, 'files' => null, 'html' => null,
            'progress' => ['message' => 'Waiting for the generator worker', 'done' => 0, 'total' => 1, 'log' => []],
        ])->save();
        GenerateUiProject::dispatch($generation->id);

        return new GenerationResource($generation);
    }

    public function mine(Request $request)
    {
        return GenerationResource::collection(
            $request->user()->generations()->with('category')->latest()->paginate(20, self::LIST_COLUMNS)
        );
    }

    /** Community showcase of generations people chose to share. */
    public function publicIndex()
    {
        return GenerationResource::collection(
            UiGeneration::where('is_public', true)->where('status', 'done')->with(['category', 'user:id,name,username,avatar_path'])->latest()->paginate(20, self::LIST_COLUMNS)
        );
    }

    public function show(Request $request, UiGeneration $generation): GenerationResource
    {
        $this->authorizeView($request, $generation);

        return new GenerationResource($generation->load(['category', 'user:id,name,username,avatar_path']));
    }

    /** Raw HTML of one screen, for "open in new tab" and downloading. Served as a sandboxed document. */
    public function raw(Request $request, UiGeneration $generation)
    {
        $this->authorizeView($request, $generation);

        $html = $generation->html;
        if ($request->filled('screen')) {
            $html = collect($generation->previews ?? [])->firstWhere('slug', $request->query('screen'))['html'] ?? null;
        }
        abort_if($html === null, 404);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => 'sandbox allow-scripts',
            'Content-Disposition' => $request->boolean('download')
                ? 'attachment; filename="uispark-'.$generation->id.'-'.Str::slug((string) $request->query('screen', 'screen')).'.html"'
                : 'inline',
        ]);
    }

    public function update(Request $request, UiGeneration $generation): GenerationResource
    {
        abort_unless($request->user()->id === $generation->user_id, 403);
        $data = $request->validate(['is_public' => ['required', 'boolean']]);
        abort_if($data['is_public'] && $generation->status !== 'done', 422, 'Only finished generations can be shared.');
        $generation->update($data);

        return new GenerationResource($generation->load('category'));
    }

    public function destroy(Request $request, UiGeneration $generation): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->id === $generation->user_id || $user->isAdmin(), 403);
        $generation->delete();

        return response()->json(['message' => 'Generation deleted.']);
    }

    // ----------------------------------------------------------------- helpers

    private function authorizeView(Request $request, UiGeneration $generation): void
    {
        $viewer = $request->user('sanctum');
        abort_unless($generation->is_public || ($viewer && ($viewer->id === $generation->user_id || $viewer->isAdmin())), 404);
    }

    /**
     * Looks the slugs up among the options this user can see, keeping the order they were picked in.
     *
     * @param  array<int, string>  $slugs
     * @return array<int, array{slug: string, name: string}>
     */
    private function resolve($user, string $type, array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }
        $found = GeneratorOption::visibleTo($user)->where('type', $type)->whereIn('slug', $slugs)->get()->keyBy('slug');

        return collect($slugs)->filter(fn ($s) => $found->has($s))
            ->map(fn ($s) => ['slug' => $s, 'name' => $found[$s]->name])->values()->all();
    }

    private function optionPayload(GeneratorOption $o): array
    {
        return [
            'id' => $o->id, 'type' => $o->type, 'slug' => $o->slug, 'name' => $o->name, 'custom' => $o->isCustom(),
        ] + ($o->type === 'framework' ? ['language' => $o->parent, 'preview_is_code' => false] : []);
    }
}
