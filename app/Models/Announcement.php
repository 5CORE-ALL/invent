<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Announcement extends Model
{
    protected $fillable = [
        'user_id',
        'message',
        'motivation',
        'images',
        'gif_url',
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

    public function reactions(): HasMany
    {
        return $this->hasMany(AnnouncementReaction::class);
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
     * Posted announcements each user has not opened. Same count as the topbar megaphone.
     *
     * @param  list<int>  $userIds
     * @return array<int, int>
     */
    public static function unseenCountsByUser(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        $out = [];
        foreach ($userIds as $userId) {
            $out[$userId] = 0;
        }
        if (
            $userIds === []
            || ! Schema::hasTable('announcements')
            || ! Schema::hasColumn('announcements', 'posted_at')
        ) {
            return $out;
        }

        $postedTotal = (int) static::query()->posted()->count();
        if ($postedTotal === 0) {
            return $out;
        }
        if (! Schema::hasTable('announcement_views')) {
            foreach ($userIds as $userId) {
                $out[$userId] = $postedTotal;
            }

            return $out;
        }

        $seen = AnnouncementView::query()
            ->join('announcements', 'announcements.id', '=', 'announcement_views.announcement_id')
            ->whereNotNull('announcements.posted_at')
            ->whereIn('announcement_views.user_id', $userIds)
            ->groupBy('announcement_views.user_id')
            ->selectRaw('announcement_views.user_id as user_id, COUNT(*) as seen')
            ->pluck('seen', 'user_id');

        foreach ($userIds as $userId) {
            $out[$userId] = max(0, $postedTotal - (int) ($seen[$userId] ?? 0));
        }

        return $out;
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
