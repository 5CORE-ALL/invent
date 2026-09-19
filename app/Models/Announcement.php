<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    protected $fillable = [
        'user_id',
        'message',
        'images',
        'announced_on',
        'posted_at',
        'created_by',
    ];

    protected $casts = [
        'images' => 'array',
        'announced_on' => 'date',
        'posted_at' => 'datetime',
    ];

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(AnnouncementView::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class)->orderBy('id');
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->whereNotNull('posted_at');
    }

    public function scopeUnreadBy(Builder $query, int $userId): Builder
    {
        return $query->whereDoesntHave('views', function (Builder $views) use ($userId) {
            $views->where('user_id', $userId);
        });
    }

    /**
     * @return list<string>
     */
    public function imagePaths(): array
    {
        $images = $this->images;
        if (! is_array($images)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($path) => trim((string) $path),
            $images
        )));
    }
}
