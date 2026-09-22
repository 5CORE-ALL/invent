<?php

namespace App\Support\Marketplace;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saved green/red pairs for /all-marketplace-master.
 * Written when channel data is calculated. Every user reads this row,
 * so a cleared cache cannot paint a different color.
 */
class ChannelMetricDotTrendStore
{
    public const TABLE = 'channel_metric_dot_trends';

    /**
     * @return array<string, mixed>|null
     */
    public static function get(int $window): ?array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return null;
        }

        $raw = DB::table(self::TABLE)->where('window', $window)->value('payload');
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function put(int $window, array $payload): void
    {
        if ($payload === [] || ! Schema::hasTable(self::TABLE)) {
            return;
        }

        $encoded = json_encode($payload);
        if (! is_string($encoded) || $encoded === '') {
            return;
        }

        DB::table(self::TABLE)->updateOrInsert(
            ['window' => $window],
            [
                'payload' => $encoded,
                'updated_at' => now(),
            ]
        );
    }
}
