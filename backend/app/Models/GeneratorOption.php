<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratorOption extends Model
{
    public const TYPES = ['language', 'framework', 'screen', 'domain'];

    protected $fillable = ['type', 'name', 'slug', 'parent', 'description', 'meta', 'user_id', 'sort_order'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Built-in rows plus the custom rows this user added. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('user_id')->when($user, fn ($q) => $q->orWhere('user_id', $user->id)));
    }

    public function isCustom(): bool
    {
        return $this->user_id !== null;
    }
}
