<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'slug'];

    public function designs(): BelongsToMany
    {
        return $this->belongsToMany(Design::class);
    }

    /** Turn ["Dark mode", "fintech"] into Tag ids, creating missing tags. */
    public static function idsFromNames(array $names): array
    {
        return collect($names)
            ->map(fn ($n) => trim(mb_strtolower((string) $n)))
            ->filter(fn ($n) => $n !== '' && mb_strlen($n) <= 40)
            ->unique()
            ->take(10)
            ->map(fn ($n) => static::firstOrCreate(['slug' => Str::slug($n)], ['name' => $n])->id)
            ->values()
            ->all();
    }
}
