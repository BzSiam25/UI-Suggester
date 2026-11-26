<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'bio', 'avatar_path', 'headline', 'location',
        'website', 'skills', 'links', 'is_profile_public',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_banned' => 'boolean',
            'is_profile_public' => 'boolean',
            'skills' => 'array',
            'links' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->username ??= static::uniqueUsername($user->name ?? 'user');
        });
    }

    /** A free handle built from the name: "Bayezid Hasan" -> "bayezidhasan", then "bayezidhasan2"... */
    public static function uniqueUsername(string $name): string
    {
        $base = Str::limit(preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($name))) ?: 'user', 20, '');
        $handle = $base;
        for ($i = 2; static::where('username', $handle)->exists(); $i++) {
            $handle = $base.$i;
        }

        return $handle;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    /** Compact author info used on cards, comments and detail pages. */
    public function card(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'username' => $this->username, 'avatar_url' => $this->avatarUrl()];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function designs(): HasMany
    {
        return $this->hasMany(Design::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(UiGeneration::class);
    }
}
