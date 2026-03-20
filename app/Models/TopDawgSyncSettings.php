<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopDawgSyncSettings extends Model
{
    protected $table = 'topdawg_sync_settings';

    protected $fillable = ['marketplace', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    public static function getForTopDawg(): array
    {
        $row = self::where('marketplace', 'topdawg')->first();
        return $row ? (array) $row->settings : self::defaults();
    }

    public static function setForTopDawg(array $settings): void
    {
        self::updateOrCreate(
            ['marketplace' => 'topdawg'],
            ['settings' => $settings]
        );
    }

    public static function defaults(): array
    {
        return [
            'general' => [
                'sync_enabled' => true,
            ],
        ];
    }
}
