<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user('sanctum');
        $canManage = $viewer && ($viewer->id === $this->user_id || $viewer->isAdmin());

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'platform' => $this->platform,
            'screen_type' => $this->screen_type,
            'image_url' => $this->imageUrl(),
            'image_width' => $this->image_width,
            'image_height' => $this->image_height,
            'source_url' => $this->source_url,
            'palette' => $this->palette ?? [],
            'status' => $this->status,
            'rejection_reason' => $this->when($canManage, $this->rejection_reason),
            'can_manage' => (bool) $canManage,
            'views_count' => $this->views_count,
            'likes_count' => $this->likes_count,
            'bookmarks_count' => $this->bookmarks_count,
            'comments_count' => $this->comments_count,
            'is_liked' => (bool) ($this->is_liked ?? false),
            'is_bookmarked' => (bool) ($this->is_bookmarked ?? false),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($t) => ['name' => $t->name, 'slug' => $t->slug])),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
