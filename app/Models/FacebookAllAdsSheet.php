<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class FacebookAllAdsSheet extends Model
{
    protected $table = 'facebook_all_ads_sheet';

    protected $fillable = [
        'import_batch_id',
        'source_filename',
        'row_index',
        'row_data',
        'ad_type',
        'ch',
        'b2b_b2c',
        'uploaded_by',
    ];

    /** Built-in Ad Type dropdown values. Extras live in facebook_ad_types. */
    public const AD_TYPES = [
        'GROUP VIDEO',
        'GROUP CAROUSAL',
        'PARENT VIDEO',
        'PARENT CAROUSAL',
        'MUSIC STORE',
        'MUSIC SCHOOL',
        'WHOLESALE',
        'DROPSHIP',
    ];

    /**
     * Built-in types plus any types created from the sheet.
     * Names are stored uppercase (WHOLESALE, DROPSHIP, …).
     */
    public static function allAdTypes(): array
    {
        $custom = [];
        if (Schema::hasTable('facebook_ad_types')) {
            $custom = FacebookAdType::query()->orderBy('name')->pluck('name')->all();
        }

        $seen = [];
        $out  = [];
        foreach (array_merge(self::AD_TYPES, $custom) as $name) {
            $key = mb_strtoupper((string) $name);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $key;
        }

        return $out;
    }

    /** Uppercase, collapse whitespace. Empty string when the input is blank. */
    public static function normalizeAdTypeName(string $raw): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($raw)) ?? '';

        return mb_strtoupper($name);
    }

    /** Allowed CH (channel) dropdown values shown on the page. */
    public const CH_OPTIONS = [
        'FB',
        'Insta',
    ];

    protected $casts = [
        'row_data' => 'array',
    ];
}
