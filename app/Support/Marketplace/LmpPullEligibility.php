<?php

namespace App\Support\Marketplace;

use App\Models\AmazonSkuCompetitor;
use App\Models\EbaySkuCompetitor;
use App\Models\GoogleSkuCompetitor;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;

/**
 * Decides which LMP competitor rows are worth a (paid) SerpApi price pull.
 *
 * A row is skipped when:
 *  - it is Ignored (any copy of the same ASIN / listing / offer counts as ignored),
 *  - our own SKU has Inv <= 0 (nothing to compare against),
 *  - (Amazon only) the competitor is out of stock — only Amazon stores stock.
 */
class LmpPullEligibility
{
    public const CHANNEL_AMAZON = 'amazon';

    public const CHANNEL_EBAY = 'ebay';

    public const CHANNEL_GOOGLE = 'google';

    /**
     * @param  iterable<mixed>  $rows  Amazon/Ebay/Google SkuCompetitor models
     * @return array{rows: Collection, skipped: array{ignored: int, no_inv: int, out_of_stock: int}}
     */
    public static function filter(iterable $rows, string $channel): array
    {
        $rows = collect($rows)->values();
        $skipped = ['ignored' => 0, 'no_inv' => 0, 'out_of_stock' => 0];

        if ($rows->isEmpty()) {
            return ['rows' => $rows, 'skipped' => $skipped];
        }

        // 1) Ignored (propagated across copies of the same ASIN / listing / offer)
        $rows = match ($channel) {
            self::CHANNEL_AMAZON => AmazonSkuCompetitor::applyIgnoreToSameAsins($rows),
            self::CHANNEL_EBAY => EbaySkuCompetitor::applyIgnoreToSameItemIds($rows),
            self::CHANNEL_GOOGLE => GoogleSkuCompetitor::applyIgnoreToSameOffers($rows),
            default => $rows,
        };
        $isIgnored = fn ($row) => match ($channel) {
            self::CHANNEL_AMAZON => AmazonSkuCompetitor::isIgnored($row),
            self::CHANNEL_EBAY => EbaySkuCompetitor::isIgnored($row),
            self::CHANNEL_GOOGLE => GoogleSkuCompetitor::isIgnored($row),
            default => false,
        };
        $before = $rows->count();
        $rows = $rows->reject($isIgnored)->values();
        $skipped['ignored'] = $before - $rows->count();

        // 2) Our Inv = 0
        if ($rows->isNotEmpty()) {
            $skus = $rows->pluck('sku')->map(fn ($s) => trim((string) $s))->filter()->unique()->values()->all();
            $shopifyBySku = ShopifySku::mapByProductSkus($skus);

            $before = $rows->count();
            $rows = $rows->filter(function ($row) use ($shopifyBySku) {
                $sku = trim((string) ($row->sku ?? ''));
                $shopify = $shopifyBySku[$sku] ?? null;

                return $shopify !== null && (float) ($shopify->inv ?? 0) > 0;
            })->values();
            $skipped['no_inv'] = $before - $rows->count();
        }

        // 3) Competitor out of stock (Amazon only)
        if ($channel === self::CHANNEL_AMAZON && $rows->isNotEmpty()) {
            $before = $rows->count();
            $rows = $rows->reject(fn ($row) => self::isAmazonOutOfStock($row))->values();
            $skipped['out_of_stock'] = $before - $rows->count();
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    public static function isAmazonOutOfStock($row): bool
    {
        $qty = $row->stock_quantity ?? null;
        if ($qty !== null && $qty !== '' && (int) $qty === 0) {
            return true;
        }

        $text = trim((string) ($row->stock ?? ''));

        return $text !== '' && (bool) preg_match('/\b(out\s+of\s+stock|currently\s+unavailable)\b/i', $text);
    }
}
