<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GenerationResource;
use App\Models\Category;
use App\Models\Design;
use App\Models\UiGeneration;
use App\Services\Generator\TemplateEngine;
use App\Services\Generator\UiGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GenerationController extends Controller
{
    private const LIST_COLUMNS = ['id', 'user_id', 'category_id', 'prompt', 'platform', 'screen_type', 'style', 'accent', 'provider', 'is_public', 'created_at'];

    /** Options for the generator form. */
    public function options(): JsonResponse
    {
        return response()->json([
            'platforms' => Design::PLATFORMS,
            'screen_types' => Design::SCREEN_TYPES,
            'styles' => array_keys(TemplateEngine::STYLES),
            'ai_enabled' => (bool) (config('services.anthropic.key') || config('services.local_ai.base_url')),
        ]);
    }

    public function store(Request $request, UiGenerator $generator): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'min:3', 'max:500'],
            'platform' => ['required', Rule::in(Design::PLATFORMS)],
            'screen_type' => ['required', Rule::in(Design::SCREEN_TYPES)],
            'style' => ['required', Rule::in(array_keys(TemplateEngine::STYLES))],
            'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'use_ai' => ['sometimes', 'boolean'],
        ]);

        $categorySlug = isset($data['category_id']) ? Category::find($data['category_id'])?->slug : null;

        $result = $generator->generate(
            $data['prompt'], $data['platform'], $data['screen_type'], $data['style'],
            $data['accent'] ?? null, $categorySlug, $request->boolean('use_ai', true),
        );

        $generation = $request->user()->generations()->create([
            ...collect($data)->except('use_ai')->all(),
            'html' => $result['html'],
            'provider' => $result['provider'],
        ]);

        return (new GenerationResource($generation->load('category')))
            ->additional(['notice' => $result['notice']])
            ->response()->setStatusCode(201);
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
            UiGeneration::where('is_public', true)->with(['category', 'user:id,name'])->latest()->paginate(20, self::LIST_COLUMNS)
        );
    }

    public function show(Request $request, UiGeneration $generation): GenerationResource
    {
        $viewer = $request->user('sanctum');
        abort_unless($generation->is_public || ($viewer && ($viewer->id === $generation->user_id || $viewer->isAdmin())), 404);

        return new GenerationResource($generation->load(['category', 'user:id,name']));
    }

    /** Raw HTML, for "open in new tab" and downloading. Served as a sandboxed document. */
    public function raw(Request $request, UiGeneration $generation)
    {
        $viewer = $request->user('sanctum');
        abort_unless($generation->is_public || ($viewer && ($viewer->id === $generation->user_id || $viewer->isAdmin())), 404);

        return response($generation->html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => 'sandbox allow-scripts',
            'Content-Disposition' => $request->boolean('download')
                ? 'attachment; filename="uispark-mockup-'.$generation->id.'.html"'
                : 'inline',
        ]);
    }

    public function update(Request $request, UiGeneration $generation): GenerationResource
    {
        abort_unless($request->user()->id === $generation->user_id, 403);
        $generation->update($request->validate(['is_public' => ['required', 'boolean']]));

        return new GenerationResource($generation->load('category'));
    }

    public function destroy(Request $request, UiGeneration $generation): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->id === $generation->user_id || $user->isAdmin(), 403);
        $generation->delete();

        return response()->json(['message' => 'Mockup deleted.']);
    }
}
