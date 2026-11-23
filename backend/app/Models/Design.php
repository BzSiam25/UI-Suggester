<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Design extends Model
{
    public const SCREEN_TYPES = [
        'landing', 'home', 'login', 'signup', 'onboarding', 'dashboard', 'product-list',
        'product-detail', 'cart', 'checkout', 'profile', 'settings', 'search', 'chat',
        'pricing', 'blog', 'empty-state', 'other',
    ];

    public const PLATFORMS = ['web', 'mobile', 'tablet'];

    public const COLOR_FAMILIES = ['red', 'orange', 'yellow', 'green', 'teal', 'blue', 'purple', 'pink', 'brown', 'black', 'white', 'gray'];

    protected $fillable = [
        'user_id', 'category_id', 'title', 'slug', 'description', 'platform', 'screen_type',
        'image_path', 'image_width', 'image_height', 'source_url', 'palette', 'color_families', 'status',
        'rejection_reason', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'palette' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Design $design) {
            if (empty($design->slug)) {
                $design->slug = Str::slug(Str::limit($design->title, 100, '')).'-'.Str::lower(Str::random(6));
            }
        });

        static::deleted(function (Design $design) {
            Storage::disk('public')->delete($design->image_path);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    public function isVisibleTo(?User $user): bool
    {
        return $this->status === 'approved'
            || ($user && ($user->id === $this->user_id || $user->isAdmin()));
    }
}
