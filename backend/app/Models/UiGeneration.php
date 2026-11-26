<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UiGeneration extends Model
{
    protected $fillable = [
        'user_id', 'category_id', 'prompt', 'platform', 'screen_type', 'style', 'accent',
        'html', 'provider', 'meta', 'is_public',
        'language', 'framework', 'screens', 'domains', 'previews', 'files',
        'status', 'progress', 'error', 'use_research',
    ];

    public const ACTIVE = ['pending', 'running'];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'use_research' => 'boolean',
            'meta' => 'array',
            'screens' => 'array',
            'domains' => 'array',
            'previews' => 'array',
            'files' => 'array',
            'progress' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Record a step so the browser can show what the generator is doing. */
    public function report(string $message, ?int $done = null, ?int $total = null): void
    {
        $progress = $this->progress ?? ['log' => []];
        $progress['message'] = $message;
        if ($done !== null) {
            $progress['done'] = $done;
        }
        if ($total !== null) {
            $progress['total'] = $total;
        }
        $progress['log'] = array_slice([...($progress['log'] ?? []), ['at' => now()->toIso8601String(), 'message' => $message]], -30);
        $this->forceFill(['progress' => $progress])->save();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
