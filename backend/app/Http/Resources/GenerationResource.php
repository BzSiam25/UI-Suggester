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
            'language' => $this->language,
            'framework' => $this->framework,
            'screens' => $this->screens ?? ($this->screen_type ? [['slug' => $this->screen_type, 'name' => ucwords(str_replace('-', ' ', $this->screen_type))]] : []),
            'domains' => $this->domains ?? [],
            'status' => $this->status ?? 'done',
            'progress' => $this->when(array_key_exists('progress', $this->resource->getAttributes()), fn () => $this->progress),
            'error' => $this->when(array_key_exists('error', $this->resource->getAttributes()), fn () => $this->error),
            'use_research' => $this->when(array_key_exists('use_research', $this->resource->getAttributes()), fn () => (bool) $this->use_research),
            // Art direction, layout recipe and the web references behind the mockup.
            'design' => $this->meta,
            'is_public' => $this->is_public,
            'is_owner' => $viewer && $viewer->id === $this->user_id,
            // List queries leave out the (large) html column, so only send it when it was selected.
            'html' => $this->when(array_key_exists('html', $this->resource->getAttributes()), fn () => $this->html),
            // Every screen's preview (older single-screen mockups only have html).
            'previews' => $this->when(array_key_exists('previews', $this->resource->getAttributes()), fn () => $this->previews
                ?? ($this->html ? [['slug' => $this->screen_type ?? 'screen', 'name' => ucwords(str_replace('-', ' ', (string) $this->screen_type)), 'html' => $this->html]] : [])),
            'files' => $this->when(array_key_exists('files', $this->resource->getAttributes()), fn () => $this->files ?? []),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'user' => $this->whenLoaded('user', fn () => $this->user->card()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
