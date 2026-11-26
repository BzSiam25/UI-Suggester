<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DesignResource;
use App\Models\Bookmark;
use App\Models\Collection;
use App\Models\Design;
use App\Models\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Likes, bookmarks and bookmark collections. */
class InteractionController extends Controller
{
    public function toggleLike(Request $request, Design $design): JsonResponse
    {
        abort_unless($design->status === 'approved', 404);
        $userId = $request->user()->id;

        $liked = DB::transaction(function () use ($design, $userId) {
            $deleted = Like::where('user_id', $userId)->where('design_id', $design->id)->delete();
            if ($deleted) {
                $design->decrement('likes_count');

                return false;
            }
            Like::create(['user_id' => $userId, 'design_id' => $design->id]);
            $design->increment('likes_count');

            return true;
        });

        return response()->json(['liked' => $liked, 'likes_count' => $design->fresh()->likes_count]);
    }

    public function toggleBookmark(Request $request, Design $design): JsonResponse
    {
        abort_unless($design->status === 'approved', 404);
        $user = $request->user();
        $data = $request->validate([
            'collection_id' => ['nullable', 'integer', Rule::exists('collections', 'id')->where('user_id', $user->id)],
        ]);

        $bookmarked = DB::transaction(function () use ($design, $user, $data) {
            $deleted = Bookmark::where('user_id', $user->id)->where('design_id', $design->id)->delete();
            if ($deleted) {
                $design->decrement('bookmarks_count');

                return false;
            }
            Bookmark::create(['user_id' => $user->id, 'design_id' => $design->id, 'collection_id' => $data['collection_id'] ?? null]);
            $design->increment('bookmarks_count');

            return true;
        });

        return response()->json(['bookmarked' => $bookmarked, 'bookmarks_count' => $design->fresh()->bookmarks_count]);
    }

    /** Move an existing bookmark into (or out of) a collection. */
    public function moveBookmark(Request $request, Design $design): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'collection_id' => ['nullable', 'integer', Rule::exists('collections', 'id')->where('user_id', $user->id)],
        ]);

        $bookmark = Bookmark::where('user_id', $user->id)->where('design_id', $design->id)->firstOrFail();
        $bookmark->update(['collection_id' => $data['collection_id'] ?? null]);

        return response()->json(['message' => 'Bookmark moved.']);
    }

    public function bookmarks(Request $request)
    {
        $user = $request->user();
        $query = Design::query()
            ->approved()
            ->join('bookmarks', 'bookmarks.design_id', '=', 'designs.id')
            ->where('bookmarks.user_id', $user->id)
            ->select('designs.*', 'bookmarks.collection_id as bookmark_collection_id')
            ->with(['category', 'user:id,name,username,avatar_path'])
            ->withExists(['likes as is_liked' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderByDesc('bookmarks.created_at');

        if ($request->filled('collection_id')) {
            $query->where('bookmarks.collection_id', $request->integer('collection_id'));
        }

        $page = $query->paginate(24);
        $page->getCollection()->each(fn ($d) => $d->is_bookmarked = true);

        return DesignResource::collection($page);
    }

    public function collections(Request $request): JsonResponse
    {
        $collections = $request->user()->collections()
            ->withCount('bookmarks')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $collections]);
    }

    public function storeCollection(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('collections')->where('user_id', $user->id)],
        ]);

        $collection = $user->collections()->create($data);

        return response()->json(['data' => $collection], 201);
    }

    public function destroyCollection(Request $request, Collection $collection): JsonResponse
    {
        abort_unless($collection->user_id === $request->user()->id, 403);
        $collection->delete(); // bookmarks stay, collection_id is nulled by the FK

        return response()->json(['message' => 'Collection deleted.']);
    }
}
