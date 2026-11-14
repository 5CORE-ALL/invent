<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * "Exec" (executive) assigned per channel (Active Channel Master column).
 */
class ChannelExecutive extends Model
{
    protected $table = 'channel_executives';

    protected $fillable = ['channel_key', 'channel', 'user_id', 'updated_by'];

    /** Same normalisation as ChannelVariationType so "eBay 3" and "ebay3" match. */
    public static function keyFor(string $channel): string
    {
        return ChannelVariationType::keyFor($channel);
    }

    /**
     * @return array<string, int> channel_key => user_id
     */
    public static function map(): array
    {
        try {
            if (! Schema::hasTable('channel_executives')) {
                return [];
            }

            return static::query()->pluck('user_id', 'channel_key')->map(fn ($v) => (int) $v)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Users that can be picked as executive: id, name and avatar url (null when no photo).
     *
     * @return array<int, array{id:int,name:string,avatar:?string}>
     */
    public static function options(): array
    {
        try {
            return User::query()
                ->where(function ($q) {
                    $q->whereNull('is_active')->orWhere('is_active', true);
                })
                ->orderBy('name')
                ->get(['id', 'name', 'avatar'])
                ->map(fn (User $u) => [
                    'id' => (int) $u->id,
                    'name' => (string) $u->name,
                    'avatar' => \App\Support\MeetingPresenter::avatarUrl($u),
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
