<?php

namespace App\Models;

use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopDawgProduct extends Model
{
    use HasFactory;

    protected $table = 'topdawg_products';

    protected $fillable = [
        'sku',
        'topdawg_listing_id',
        'tdid',
        'image_src',
        'listing_state',
        'product_title',
        'r_l30',
        'r_l60',
        'price',
        'msrp',
        'views',
        'remaining_inventory',
    ];

    /**
     * True when this row is the SKU's own live TopDawg catalog listing.
     * Uploaded / review / SKU-as-id placeholders are not listed.
     */
    public function countsAsLiveCatalogListing(): bool
    {
        if (ListingCountsEngine::isPendingOrReviewListingState($this->listing_state)) {
            return false;
        }

        $sku = trim((string) $this->sku);
        $listingId = trim((string) ($this->topdawg_listing_id ?? ''));
        $tdid = trim((string) ($this->tdid ?? ''));

        return ChannelListingRegistry::isLiveTopDawgListingId($listingId, $sku)
            || ChannelListingRegistry::isLiveTopDawgListingId($tdid, $sku);
    }

    /**
     * Normalized SKU => row (case / Unicode space tolerant, same rules as ShopifySku).
     *
     * Pack / combo aliases are off for the analytics price column: a listed
     * "HW 405 BLK 2PCS" must not put a price on unlisted "HW 405 BLK".
     *
     * @param  array<int, string>  $productSkus
     * @return array<string, self>
     */
    public static function buildLookupByNormalizedSku(array $productSkus, bool $withAliases = true, bool $liveOnly = false): array
    {
        $productSkus = array_values(array_filter(array_map(
            static fn ($sku) => trim((string) $sku),
            $productSkus
        )));

        if ($productSkus === []) {
            return [];
        }

        $accept = static function (self $row) use ($liveOnly): bool {
            return ! $liveOnly || $row->countsAsLiveCatalogListing();
        };

        $lookup = [];
        foreach (self::whereIn('sku', $productSkus)->get() as $row) {
            if (! $accept($row)) {
                continue;
            }
            $key = ShopifySku::normalizeSkuForShopifyLookup($row->sku);
            if ($key !== '' && ! isset($lookup[$key])) {
                $lookup[$key] = $row;
            }
        }

        $missing = [];
        foreach ($productSkus as $pmSku) {
            $key = ShopifySku::normalizeSkuForShopifyLookup($pmSku);
            if ($key !== '' && ! isset($lookup[$key])) {
                $missing[$key] = true;
            }
        }

        if ($missing === []) {
            return $lookup;
        }

        self::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$lookup, &$missing, $accept) {
                foreach ($rows as $row) {
                    if (! $accept($row)) {
                        continue;
                    }
                    $key = ShopifySku::normalizeSkuForShopifyLookup($row->sku);
                    if ($key !== '' && isset($missing[$key]) && ! isset($lookup[$key])) {
                        $lookup[$key] = $row;
                        unset($missing[$key]);
                    }
                }

                return count($missing) > 0;
            });

        if ($missing === [] || ! $withAliases) {
            return $lookup;
        }

        // Pack / family aliases (same rules as listedTopDawg). Analytics price
        // passes $withAliases = false so an unlisted SKU stays at price 0.
        $wantedAliasToKey = [];
        foreach (array_keys($missing) as $key) {
            foreach (ListingCountsEngine::skuAliasBases($key) as $base) {
                if ($base !== '' && ! isset($wantedAliasToKey[$base])) {
                    $wantedAliasToKey[$base] = $key;
                }
            }
        }

        if ($wantedAliasToKey !== []) {
            self::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use (&$lookup, &$missing, $wantedAliasToKey, $accept) {
                    foreach ($rows as $row) {
                        if ($missing === []) {
                            return false;
                        }
                        if (! $accept($row)) {
                            continue;
                        }
                        foreach (ListingCountsEngine::skuAliasBases((string) $row->sku) as $base) {
                            $target = $wantedAliasToKey[$base] ?? null;
                            if ($target !== null && isset($missing[$target]) && ! isset($lookup[$target])) {
                                $lookup[$target] = $row;
                                unset($missing[$target]);
                                break;
                            }
                        }
                    }

                    return count($missing) > 0;
                });
        }

        return $lookup;
    }
}
