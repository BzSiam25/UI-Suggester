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
            'bio' => $this->bio,
            'role' => $this->role,
            'email' => $this->when($private, $this->email),
            'is_banned' => $this->when($private, (bool) $this->is_banned),
            'designs_count' => $this->whenCounted('designs'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
