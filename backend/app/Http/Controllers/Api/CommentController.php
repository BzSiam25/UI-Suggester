<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Design;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function index(Request $request, Design $design)
    {
        abort_unless($design->isVisibleTo($request->user('sanctum')), 404);

        $comments = $design->comments()
            ->whereNull('parent_id')
            ->with(['user:id,name', 'replies.user:id,name'])
            ->latest()
            ->paginate(20);

        return CommentResource::collection($comments);
    }

    public function store(Request $request, Design $design): JsonResponse
    {
        abort_unless($design->status === 'approved', 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:1000'],
            // Replies are one level deep: the parent must be a top-level comment on this design.
            'parent_id' => ['nullable', 'integer', Rule::exists('comments', 'id')
                ->where('design_id', $design->id)
                ->whereNull('parent_id')],
        ]);

        $comment = DB::transaction(function () use ($design, $data, $request) {
            $comment = $design->comments()->create([
                'user_id' => $request->user()->id,
                'parent_id' => $data['parent_id'] ?? null,
                'body' => trim($data['body']),
            ]);
            $design->increment('comments_count');

            return $comment;
        });

        $comment->load('user:id,name')->setRelation('replies', collect());

        return (new CommentResource($comment))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->id === $comment->user_id || $user->isAdmin(), 403);

        DB::transaction(function () use ($comment) {
            $removed = 1 + $comment->replies()->count();
            $comment->delete(); // replies cascade
            Design::whereKey($comment->design_id)
                ->where('comments_count', '>=', $removed)
                ->decrement('comments_count', $removed);
        });

        return response()->json(['message' => 'Comment deleted.']);
    }
}
