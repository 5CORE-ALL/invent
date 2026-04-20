<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MarketplacePercentage extends Model
{
    use SoftDeletes;

    protected $table = 'marketplace_percentages';

    protected $fillable = ['marketplace', 'percentage','ad_updates'];

    protected $dates = ['deleted_at'];

    /**
     * Label from `marketplace_percentages.marketplace` for a row in `marketplaces`, when names align.
     * Used by SKU Image Manager so UI matches the percentage master list.
     */
    public static function displayNameForMarketplace(?Marketplace $marketplace): ?string
    {
        if ($marketplace === null) {
            return null;
        }

        foreach (self::matchNeedlesForMarketplace($marketplace) as $needle) {
            $label = static::query()
                ->whereRaw('LOWER(TRIM(marketplace)) = ?', [$needle])
                ->orderBy('id')
                ->value('marketplace');
            if ($label !== null && $label !== '') {
                return (string) $label;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function matchNeedlesForMarketplace(Marketplace $marketplace): array
    {
        $candidates = [
            strtolower(trim((string) $marketplace->name)),
            strtolower(trim((string) $marketplace->code)),
            strtolower(Str::studly((string) $marketplace->code)),
            strtolower(str_replace(['_', '-', ' '], '', (string) $marketplace->code)),
            strtolower(str_replace(['_', '-', ' '], '', (string) $marketplace->name)),
        ];

        $out = [];
        foreach ($candidates as $c) {
            if ($c !== '') {
                $out[] = $c;
            }
        }

        return array_values(array_unique($out));
    }
}