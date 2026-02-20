<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ReverbSyncState extends Model
{
    protected $table = 'reverb_sync_states';

    protected $fillable = ['key', 'value'];

    public const KEY_ORDERS_LAST_SYNC = 'orders_last_sync';
    public const KEY_INVENTORY_LAST_SYNC = 'inventory_last_sync';

    public static function getLastSync(string $key): ?Carbon
    {
        $row = self::where('key', $key)->first();
        if (!$row || !$row->value) {
            return null;
        }
        try {
            return Carbon::parse($row->value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function setLastSync(string $key, ?Carbon $at = null): void
    {
        $at = $at ?? now();
        self::updateOrCreate(
            ['key' => $key],
            ['value' => $at->toIso8601String()]
        );
    }
}
