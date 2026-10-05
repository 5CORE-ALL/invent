<?php

namespace App\Http\Controllers\Campaigns;

use App\Models\WalmartCampaignReport;
use App\Models\WalmartDataView;
use App\Models\WalmartProductSheet;
use App\Support\Ads\MissingAdsCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Walmart sheet SKUs with no campaign and Shopify Inv > 0.
 */
class WalmartMissingAdsController extends ListingMissingAdsController
{
    protected function cacheKey(): string
    {
        return 'walmart_ads_missing_sidebar_count';
    }

    protected function pageTitle(): string
    {
        return 'Walmart Missing Ads';
    }

    protected function pageSubtitle(): string
    {
        return 'Walmart listings with no campaign and Inv > 0.';
    }

    protected function adsUrl(): string
    {
        return route('walmart.running.ads');
    }

    protected function adsLabel(): string
    {
        return 'Walmart Running Ads';
    }

    protected function idField(): string
    {
        return 'sku';
    }

    protected function idLabel(): string
    {
        return 'SKU';
    }

    public static function dataRouteName(): string
    {
        return 'walmart.missing.ads.data';
    }

    protected function collectMissingRows(bool $withImages = true): Collection
    {
        if (! Schema::hasTable('walmart_product_sheet')) {
            return collect();
        }

        $campaignSkus = [];
        if (Schema::hasTable('walmart_campaign_reports')) {
            foreach (WalmartCampaignReport::query()->pluck('campaignName') as $name) {
                $key = $this->norm((string) $name);
                if ($key !== '') {
                    $campaignSkus[$key] = true;
                }
            }
        }

        $nrSkus = [];
        if (Schema::hasTable('walmart_data_view')) {
            foreach (WalmartDataView::query()->get(['sku', 'value']) as $row) {
                $raw = $row->value;
                if (! is_array($raw)) {
                    $raw = json_decode((string) $raw, true);
                }
                $flag = is_array($raw) ? strtoupper(trim((string) ($raw['NR'] ?? ''))) : '';
                if ($flag === 'NRA') {
                    $nrSkus[$this->norm((string) $row->sku)] = true;
                }
            }
        }

        $candidates = collect();
        foreach (WalmartProductSheet::query()->pluck('sku') as $sku) {
            $sku = trim((string) $sku);
            $key = $this->norm($sku);
            if ($key === '' || isset($candidates[$key]) || isset($campaignSkus[$key]) || isset($nrSkus[$key])) {
                continue;
            }
            $candidates[$key] = [
                'sku' => $sku,
                'product_id' => $sku,
            ];
        }

        return MissingAdsCatalog::requireShopifyInventory($candidates->values(), $withImages)
            ->sortBy(fn (array $row) => strtoupper((string) ($row['sku'] ?? '')))
            ->values();
    }

    private function norm(string $sku): string
    {
        $sku = str_replace("\xc2\xa0", ' ', $sku);

        return strtoupper(trim((string) preg_replace('/\s+/', ' ', $sku)));
    }
}
