<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user('sanctum');
        $private = $viewer && ($viewer->id === $this->id || $viewer->isAdmin());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'avatar_url' => $this->avatarUrl(),
            'headline' => $this->headline,
            'bio' => $this->bio,
            'location' => $this->location,
            'website' => $this->website,
            'skills' => $this->skills ?? [],
            'links' => (object) ($this->links ?? []),
            'is_profile_public' => (bool) ($this->is_profile_public ?? true),
            'role' => $this->role,
            'email' => $this->when($private, $this->email),
            'is_banned' => $this->when($private, (bool) $this->is_banned),
            'designs_count' => $this->whenCounted('designs'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
