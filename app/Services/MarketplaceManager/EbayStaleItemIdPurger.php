<?php

namespace App\Services\MarketplaceManager;

use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingManagerEbayTradingPublisher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * A SKU that holds an eBay item id although eBay no longer has the SKU on that listing (listing deleted or
 * ended, SKU dropped from its variation listing, or an id that belongs to another seller) must not count as
 * Listed. Every live item id is asked about on eBay (GetItem); the dead ones lose the id and the SKU shows
 * on Missing L, to be listed again as a new product.
 *
 * Used by the "Fetch listings" button, the daily schedule and `ebay:purge-stale-item-ids`.
 */
class EbayStaleItemIdPurger
{
    private const SLUGS = [1 => 'ebay', 2 => 'ebaytwo', 3 => 'ebaythree'];

    /**
     * @param  list<string>  $skus  only these SKUs (empty = every live row)
     * @param  callable(string): void|null  $log  receives one line per checked row
     * @return array{ok: bool, message: string, checked: int, cleared: int, live: int, errors: int, aborted: bool}
     */
    public function purge(int $store, array $skus = [], bool $zeroOnly = false, int $limit = 3000, bool $dry = false, ?callable $log = null): array
    {
        $out = ['ok' => false, 'message' => '', 'checked' => 0, 'cleared' => 0, 'live' => 0, 'errors' => 0, 'aborted' => false];
        $slug = self::SLUGS[$store] ?? null;
        $cfg = $slug !== null ? ChannelListingRegistry::get($slug) : null;
        $model = (string) ($cfg['listed']['model'] ?? '');
        if ($slug === null || $model === '' || ! class_exists($model)) {
            $out['message'] = "eBay {$store}: metric model not found.";

            return $out;
        }
        $table = (new $model)->getTable();
        $say = $log ?? static function (string $line): void {};
        $skuFilter = array_values(array_filter(array_map(static fn ($s) => strtoupper(trim((string) $s)), $skus)));
        $notLive = array_map('strtoupper', ChannelListingRegistry::EBAY_NOT_LIVE_STATUSES);
        $hasStatus = Schema::hasColumn($table, 'listing_status');
        $hasReason = Schema::hasColumn($table, 'inactive_reason');
        $hasLink = Schema::hasColumn($table, 'ebay_link');
        $hasStock = Schema::hasColumn($table, 'ebay_stock');
        $blank = $table === 'ebay_2_metrics' ? '' : null; // ebay_2_metrics.item_id is NOT NULL

        $query = $model::query()
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('item_id')->where('item_id', '!=', '')
            ->where('item_id', 'NOT LIKE', 'UNSOLD-%')
            ->whereColumn('item_id', '!=', 'sku');
        if ($skuFilter !== []) {
            $query->where(function ($q) use ($skuFilter) {
                foreach ($skuFilter as $sku) {
                    $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$sku]);
                }
            });
        } elseif ($zeroOnly && $hasStock) {
            $query->whereNotNull('ebay_stock')->where('ebay_stock', '<=', 0);
        }

        $rows = $query->get()->filter(function ($row) use ($hasStatus, $notLive) {
            if (stripos((string) $row->sku, 'PARENT') !== false) {
                return false;
            }
            $status = $hasStatus ? strtoupper(trim((string) ($row->listing_status ?? ''))) : '';

            return ! in_array($status, $notLive, true);
        })->values();

        $consecutiveErrors = 0;
        foreach ($rows->take(max(1, $limit)) as $row) {
            $sku = trim((string) $row->sku);
            $itemId = trim((string) $row->item_id);
            $res = ListingManagerEbayTradingPublisher::itemState($slug, $itemId, $sku);
            $state = $res['state'];
            $out['checked']++;

            if ($state === 'error') {
                $out['errors']++;
                $consecutiveErrors++;
                $say("{$sku} ({$itemId}): could not verify - {$res['message']}");
                if ($consecutiveErrors >= 5) {
                    $out['aborted'] = true;
                    $say('Five failures in a row (rate limit or credentials); stopping. Run again later.');
                    break;
                }

                continue;
            }
            $consecutiveErrors = 0;

            if ($state === 'live') {
                $out['live']++;
                $say("{$sku} ({$itemId}): still live on eBay, qty ".($res['quantity'] ?? '?').' - kept');

                continue;
            }

            // not_found / ended / sku_absent / foreign: this item id no longer holds the SKU.
            $say("{$sku} ({$itemId}): {$state} - ".($dry ? 'would clear' : 'cleared').' ('.$res['message'].')');
            $out['cleared']++;
            if ($dry) {
                continue;
            }

            $payload = ['item_id' => $blank];
            if ($hasStatus) {
                $payload['listing_status'] = 'MISSING';
            }
            if ($hasReason) {
                $payload['inactive_reason'] = 'eBay no longer has this SKU on that listing ('.$state.')';
            }
            if ($hasLink) {
                $payload['ebay_link'] = null;
            }
            try {
                $this->clearRow($model, $row, $payload, $sku, $blank);
                Log::info("EbayStaleItemIdPurger eBay {$store}: cleared item id", ['sku' => $sku, 'item_id' => $itemId, 'state' => $state]);
            } catch (\Throwable $e) {
                $out['cleared']--;
                $out['errors']++;
                $say("{$sku} ({$itemId}): could not update the row - ".$e->getMessage());
                Log::warning('EbayStaleItemIdPurger: update failed', ['sku' => $sku, 'item_id' => $itemId, 'error' => $e->getMessage()]);
            }
        }

        $out['ok'] = ! $out['aborted'];
        $out['message'] = "Checked {$out['checked']} listing row(s) on eBay: {$out['cleared']} no longer on eBay".($dry ? ' (dry run)' : ' (now Missing L)')
            .", {$out['live']} still live, {$out['errors']} could not be verified.";

        return $out;
    }

    /**
     * Blank the item id of a stale row. The table is unique on (item_id, sku): when the SKU already has a blank
     * row (an earlier stale id was just cleared), this row is a leftover duplicate and is removed instead.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  array<string, mixed>  $payload
     */
    private function clearRow(string $model, $row, array $payload, string $sku, $blank): void
    {
        $dupe = $model::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->whereKeyNot($row->getKey())
            ->where(function ($q) {
                $q->whereNull('item_id')->orWhere('item_id', '');
            })
            ->exists();
        if ($dupe) {
            $model::query()->whereKey($row->getKey())->delete();

            return;
        }

        try {
            $model::query()->whereKey($row->getKey())->update($payload);
        } catch (\Illuminate\Database\QueryException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
            $model::query()->whereKey($row->getKey())->delete();
        }
    }
}
