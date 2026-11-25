<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GenerationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user('sanctum');

        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'platform' => $this->platform,
            'screen_type' => $this->screen_type,
            'style' => $this->style,
            'accent' => $this->accent,
            'provider' => $this->provider,
            // Art direction, layout recipe and the web references behind the mockup.
            'design' => $this->meta,
            'is_public' => $this->is_public,
            'is_owner' => $viewer && $viewer->id === $this->user_id,
            // List queries leave out the (large) html column, so only send it when it was selected.
            'html' => $this->when(array_key_exists('html', $this->resource->getAttributes()), fn () => $this->html),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
