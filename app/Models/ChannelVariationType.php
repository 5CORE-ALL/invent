<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * "Variation / Single" setting per channel (Active Channel Master column).
 */
class ChannelVariationType extends Model
{
    public const TYPES = ['Variation', 'Single'];

    protected $table = 'channel_variation_types';

    protected $fillable = ['channel_key', 'channel', 'variation_type', 'updated_by'];

    /** Lowercase letters/digits only, so "eBay 3" and "ebay3" are the same channel. */
    public static function keyFor(string $channel): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', trim($channel)));
    }

    /**
     * @return array<string, string> channel_key => 'Variation'|'Single'
     */
    public static function map(): array
    {
        try {
            if (! Schema::hasTable('channel_variation_types')) {
                return [];
            }

            return static::query()->pluck('variation_type', 'channel_key')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
