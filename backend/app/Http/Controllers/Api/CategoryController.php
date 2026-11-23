<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Design;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::query()
            ->withCount(['designs' => fn ($q) => $q->approved()])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    /** Everything the filter bar and forms need in one request. */
    public function meta(): JsonResponse
    {
        return response()->json([
            'platforms' => Design::PLATFORMS,
            'screen_types' => Design::SCREEN_TYPES,
            'colors' => Design::COLOR_FAMILIES,
            'popular_tags' => Tag::query()
                ->withCount(['designs' => fn ($q) => $q->approved()])
                ->having('designs_count', '>', 0)
                ->orderByDesc('designs_count')
                ->limit(20)
                ->get(['id', 'name', 'slug'])
                ->map(fn ($t) => ['name' => $t->name, 'slug' => $t->slug, 'count' => $t->designs_count]),
        ]);
    }
}
