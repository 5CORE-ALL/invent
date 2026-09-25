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

    /**
     * Saved tag, otherwise a B2B / B2C sheet column, otherwise one B2B or
     * B2C token in the campaign name.
     *
     * @param  array<string, mixed>  $rowData
     */
    public static function resolveB2bB2c(?string $stored, array $rowData): ?string
    {
        try {
            $options = FacebookB2bB2cOption::options();
        } catch (\Throwable) {
            $options = FacebookB2bB2cOption::BUILTIN;
        }

        $storedKey = self::normalizeAdTypeName((string) $stored);
        if ($storedKey !== '' && in_array($storedKey, $options, true)) {
            return $storedKey;
        }

        foreach ($rowData as $key => $value) {
            if (! self::isB2bB2cHeader((string) $key)) {
                continue;
            }
            $fromColumn = self::normalizeAdTypeName((string) $value);
            if ($fromColumn !== '' && in_array($fromColumn, $options, true)) {
                return $fromColumn;
            }
        }

        $name = $rowData['Campaign name'] ?? $rowData['Campaign Name'] ?? null;

        return self::b2bTokenInText(is_string($name) ? $name : null, $options);
    }

    public static function isB2bB2cHeader(string $key): bool
    {
        $n = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', $key));

        return $n !== '' && str_contains($n, 'B2B') && str_contains($n, 'B2C');
    }

    /**
     * @param  list<string>  $options
     */
    public static function b2bTokenInText(?string $text, array $options): ?string
    {
        $text = self::normalizeAdTypeName((string) $text);
        if ($text === '') {
            return null;
        }

        $hits = [];
        foreach ($options as $opt) {
            $quoted = preg_quote($opt, '/');
            if (preg_match('/(?<![A-Z0-9])'.$quoted.'(?![A-Z0-9])/', $text)) {
                $hits[] = $opt;
            }
        }

        return count($hits) === 1 ? $hits[0] : null;
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
