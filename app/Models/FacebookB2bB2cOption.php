<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class FacebookB2bB2cOption extends Model
{
    protected $table = 'facebook_b2b_b2c_options';

    protected $fillable = ['name'];

    public const BUILTIN = [
        'B2B',
        'B2C',
    ];

    /**
     * Built-in choices plus anything saved from the sheet. Names are unique
     * after the same uppercase / collapsed-space rules as Ad Type.
     *
     * @return list<string>
     */
    public static function options(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $custom = [];
        if (Schema::hasTable('facebook_b2b_b2c_options')) {
            $custom = self::query()->orderBy('name')->pluck('name')->all();
        }

        $seen = [];
        $out = [];
        foreach (array_merge(self::BUILTIN, $custom) as $name) {
            $key = FacebookAllAdsSheet::normalizeAdTypeName((string) $name);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $key;
        }

        return $cached = $out;
    }
}
