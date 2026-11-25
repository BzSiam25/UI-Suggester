<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DesignResource;
use App\Models\Category;
use App\Models\Design;
use App\Models\Tag;
use App\Services\PaletteExtractor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DesignController extends Controller
{
    /** Public gallery: search, filter and sort approved designs. */
    public function index(Request $request)
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:90'],
            'platform' => ['nullable', Rule::in(Design::PLATFORMS)],
            'screen_type' => ['nullable', Rule::in(Design::SCREEN_TYPES)],
            'color' => ['nullable', Rule::in(Design::COLOR_FAMILIES)],
            'tag' => ['nullable', 'string', 'max:50'],
            'sort' => ['nullable', Rule::in(['latest', 'popular', 'trending', 'most_viewed'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $query = Design::query()->approved()->with(['category', 'user:id,name']);
        $this->applyFilters($query, $request);
        $this->withViewerFlags($query, $request);

        match ($request->input('sort', 'latest')) {
            'popular' => $query->orderByDesc('likes_count')->orderByDesc('approved_at'),
            'most_viewed' => $query->orderByDesc('views_count'),
            'trending' => $query
                ->withCount(['likes as recent_likes' => fn ($q) => $q->where('created_at', '>=', now()->subDays(7))])
                ->orderByDesc('recent_likes')->orderByDesc('approved_at'),
            default => $query->orderByDesc('approved_at')->orderByDesc('id'),
        };

        return DesignResource::collection($query->paginate($request->integer('per_page', 24))->withQueryString());
    }

    public function show(Request $request, Design $design): DesignResource
    {
        abort_unless($design->isVisibleTo($request->user('sanctum')), 404);

        if ($design->status === 'approved') {
            $design->increment('views_count');
        }

        $design->load(['category', 'user:id,name', 'tags']);
        $this->attachViewerFlags($design, $request);

        return new DesignResource($design);
    }

    /** "More like this": same domain or same screen type. */
    public function similar(Request $request, Design $design)
    {
        $query = Design::query()->approved()
            ->whereKeyNot($design->id)
            ->where(fn ($q) => $q->where('category_id', $design->category_id)->orWhere('screen_type', $design->screen_type))
            ->with(['category', 'user:id,name'])
            ->orderByRaw('CASE WHEN category_id = ? AND screen_type = ? THEN 0 WHEN category_id = ? THEN 1 ELSE 2 END', [$design->category_id, $design->screen_type, $design->category_id])
            ->orderByDesc('likes_count')
            ->limit(8);
        $this->withViewerFlags($query, $request);

        return DesignResource::collection($query->get());
    }

    /** Community upload. Goes to the moderation queue unless an admin uploads it. */
    public function store(Request $request, PaletteExtractor $palette): JsonResponse
    {
        $data = $this->validateDesign($request, true);
        $user = $request->user();

        $file = $request->file('image');
        $path = $file->store('designs/'.now()->format('Y/m'), 'public');
        $absolute = Storage::disk('public')->path($path);
        [$width, $height] = file_exists($absolute) ? (getimagesize($absolute) ?: [null, null]) : [null, null];
        $colors = file_exists($absolute) ? $palette->extract($absolute) : [];

        $design = Design::create([
            ...collect($data)->except(['image', 'tags'])->all(),
            'user_id' => $user->id,
            'image_path' => $path,
            'image_width' => $width,
            'image_height' => $height,
            'palette' => $colors,
            'color_families' => PaletteExtractor::families($colors),
            'status' => $user->isAdmin() ? 'approved' : 'pending',
            'approved_at' => $user->isAdmin() ? now() : null,
        ]);
        $design->tags()->sync(Tag::idsFromNames($data['tags'] ?? []));

        return (new DesignResource($design->load(['category', 'user:id,name', 'tags'])))
            ->additional(['message' => $design->status === 'approved'
                ? 'Design published.'
                : 'Thanks! Your design is waiting for review.'])
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, Design $design): DesignResource
    {
        $user = $request->user();
        abort_unless($user->id === $design->user_id || $user->isAdmin(), 403);

        $data = $this->validateDesign($request, false);
        $design->fill(collect($data)->except(['tags', 'image'])->all());

        // An owner fixing a rejected design sends it back to the queue.
        if (! $user->isAdmin() && $design->status === 'rejected') {
            $design->status = 'pending';
            $design->rejection_reason = null;
        }
        $design->save();

        if ($request->has('tags')) {
            $design->tags()->sync(Tag::idsFromNames($data['tags'] ?? []));
        }

        return new DesignResource($design->load(['category', 'user:id,name', 'tags']));
    }

    public function destroy(Request $request, Design $design): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->id === $design->user_id || $user->isAdmin(), 403);
        $design->delete();

        return response()->json(['message' => 'Design deleted.']);
    }

    /** The signed-in user's own uploads, in every status. */
    public function mine(Request $request)
    {
        $query = $request->user()->designs()->with(['category', 'user:id,name'])->latest();
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return DesignResource::collection($query->paginate(24));
    }

    // ---------------------------------------------------------------- helpers

    private function validateDesign(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'title' => [$req, 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => [$req, 'integer', 'exists:categories,id'],
            'platform' => [$req, Rule::in(Design::PLATFORMS)],
            'screen_type' => [$req, Rule::in(Design::SCREEN_TYPES)],
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:40'],
            'image' => $creating
                ? ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=200,min_height=200']
                : ['prohibited'],
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($q = trim((string) $request->input('q'))) {
            $query->where(fn ($w) => $w
                ->where('title', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
                ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', "%{$q}%"))
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$q}%")));
        }
        if ($slug = $request->input('category')) {
            $query->whereIn('category_id', Category::where('slug', $slug)->select('id'));
        }
        if ($platform = $request->input('platform')) {
            $query->where('platform', $platform);
        }
        if ($screen = $request->input('screen_type')) {
            $query->where('screen_type', $screen);
        }
        if ($color = $request->input('color')) {
            // color_families is a space-separated list like "white blue gray"
            $query->where(fn ($w) => $w
                ->where('color_families', $color)
                ->orWhere('color_families', 'like', "{$color} %")
                ->orWhere('color_families', 'like', "% {$color}")
                ->orWhere('color_families', 'like', "% {$color} %"));
        }
        if ($tag = $request->input('tag')) {
            $query->whereHas('tags', fn ($t) => $t->where('slug', $tag));
        }
    }

    private function withViewerFlags(Builder $query, Request $request): void
    {
        if ($user = $request->user('sanctum')) {
            $query->withExists([
                'likes as is_liked' => fn ($q) => $q->where('user_id', $user->id),
                'bookmarks as is_bookmarked' => fn ($q) => $q->where('user_id', $user->id),
            ]);
        }
    }

    private function attachViewerFlags(Design $design, Request $request): void
    {
        if ($user = $request->user('sanctum')) {
            $design->is_liked = $design->likes()->where('user_id', $user->id)->exists();
            $design->is_bookmarked = $design->bookmarks()->where('user_id', $user->id)->exists();
        }
    }
}
