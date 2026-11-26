<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\DesignResource;
use App\Http\Resources\UserResource;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Design;
use App\Models\UiGeneration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    // -------------------------------------------------------------- dashboard

    public function stats(): JsonResponse
    {
        $since = now()->subDays(13)->startOfDay();

        $uploads = Design::where('created_at', '>=', $since)->get(['created_at'])
            ->groupBy(fn ($d) => $d->created_at->toDateString())
            ->map->count();
        $daily = collect(range(13, 0))->map(function ($ago) use ($uploads) {
            $day = now()->subDays($ago)->toDateString();

            return ['date' => $day, 'uploads' => $uploads[$day] ?? 0];
        })->values();

        return response()->json([
            'totals' => [
                'users' => User::count(),
                'designs' => Design::where('status', 'approved')->count(),
                'pending' => Design::where('status', 'pending')->count(),
                'rejected' => Design::where('status', 'rejected')->count(),
                'comments' => Comment::count(),
                'generations' => UiGeneration::count(),
                'likes' => (int) Design::sum('likes_count'),
            ],
            'daily_uploads' => $daily,
            'by_category' => Category::withCount(['designs' => fn ($q) => $q->approved()])
                ->orderByDesc('designs_count')->get(['id', 'name', 'icon'])
                ->map(fn ($c) => ['name' => $c->name, 'icon' => $c->icon, 'count' => $c->designs_count]),
            'by_platform' => Design::approved()->selectRaw('platform, COUNT(*) as count')->groupBy('platform')->pluck('count', 'platform'),
            'top_designs' => DesignResource::collection(
                Design::approved()->with(['category', 'user:id,name,username,avatar_path'])->orderByDesc('likes_count')->limit(5)->get()
            ),
        ]);
    }

    // ------------------------------------------------------------- moderation

    public function designs(Request $request)
    {
        $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])]]);

        $query = Design::query()->with(['category', 'user:id,name,username,avatar_path', 'tags']);
        if ($status = $request->input('status', 'pending')) {
            $query->where('status', $status);
        }
        if ($q = $request->input('q')) {
            $query->where('title', 'like', "%{$q}%");
        }
        // Oldest first for the queue, newest first otherwise.
        $status === 'pending' ? $query->oldest() : $query->latest();

        return DesignResource::collection($query->paginate(20)->withQueryString());
    }

    public function approve(Design $design): DesignResource
    {
        $design->update(['status' => 'approved', 'rejection_reason' => null, 'approved_at' => $design->approved_at ?? now()]);

        return new DesignResource($design->load(['category', 'user:id,name,username,avatar_path', 'tags']));
    }

    public function reject(Request $request, Design $design): DesignResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $design->update(['status' => 'rejected', 'rejection_reason' => $data['reason']]);

        return new DesignResource($design->load(['category', 'user:id,name,username,avatar_path', 'tags']));
    }

    // ------------------------------------------------------------------ users

    public function users(Request $request)
    {
        $query = User::query()->withCount('designs')->latest();
        if ($q = $request->input('q')) {
            $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
        }

        return UserResource::collection($query->paginate(25)->withQueryString());
    }

    public function updateUser(Request $request, User $user): UserResource
    {
        $data = $request->validate([
            'role' => ['sometimes', Rule::in(['user', 'admin'])],
            'is_banned' => ['sometimes', 'boolean'],
        ]);
        abort_if($user->id === $request->user()->id, 422, 'You cannot change your own role or ban yourself.');

        $user->forceFill($data)->save();
        if (! empty($data['is_banned'])) {
            $user->tokens()->delete(); // sign them out everywhere
        }

        return new UserResource($user->loadCount('designs'));
    }

    // ------------------------------------------------------------- categories

    public function storeCategory(Request $request): CategoryResource
    {
        $data = $this->validateCategory($request);
        $data['slug'] = Str::slug($data['name']);
        abort_if(Category::where('slug', $data['slug'])->exists(), 422, 'A category with that name already exists.');

        return new CategoryResource(Category::create($data));
    }

    public function updateCategory(Request $request, Category $category): CategoryResource
    {
        $category->update($this->validateCategory($request, $category));

        return new CategoryResource($category);
    }

    public function destroyCategory(Category $category): JsonResponse
    {
        abort_if($category->designs()->exists(), 422, 'Move or delete this category\'s designs first.');
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    private function validateCategory(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => [$category ? 'sometimes' : 'required', 'string', 'max:80'],
            'icon' => ['nullable', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
