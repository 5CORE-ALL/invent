<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdsLinkCampaign extends Model
{
    protected $table = 'ads_link_campaigns';

    protected $fillable = [
        'sku',
        'sku_norm',
        'campaign_name',
        'campaign_id',
        'updated_by',
    ];

    public static function normalizeSku(string $sku): string
    {
        return strtoupper(trim($sku));
    }

    /**
     * @param  list<string>  $skus
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, self>>
     */
    public static function groupBySkus(array $skus)
    {
        $norms = collect($skus)
            ->map(fn ($sku) => self::normalizeSku((string) $sku))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($norms === []) {
            return collect();
        }

        return self::query()
            ->whereIn('sku_norm', $norms)
            ->orderBy('id')
            ->get()
            ->groupBy('sku_norm');
    }
}
