<?php

namespace App\Services\Support\Concerns;

use App\Services\Support\EbaySellInventoryListingResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve eBay ItemID for Trading API pushes when metrics row is missing item_id.
 */
trait ResolvesEbayListingItemId
{
    /**
     * @param  array<string, string>  $tradingHeaders
     * @return array{item_id: ?string, row: ?object}
     */
    protected function resolveEbayItemIdForPush(
        string $metricsTable,
        string $identifier,
        string $bearerToken,
        string $tradingEndpoint,
        array $tradingHeaders,
    ): array {
        $row = $this->findMetricRowBySkuOrAlternateIds($metricsTable, $identifier, ['item_id']);
        $itemId = isset($row->item_id) && trim((string) $row->item_id) !== ''
            ? trim((string) $row->item_id)
            : null;

        if (! $itemId && $bearerToken !== '') {
            $itemId = EbaySellInventoryListingResolver::resolveWithTradingFallback(
                $bearerToken,
                $tradingEndpoint,
                $tradingHeaders,
                trim($identifier)
            );
            if ($itemId && Schema::hasTable($metricsTable)) {
                $this->upsertEbayMetricsItemId($metricsTable, $identifier, $itemId, $row);
            }
        }

        return ['item_id' => $itemId, 'row' => $row];
    }

    protected function upsertEbayMetricsItemId(string $table, string $sku, string $itemId, ?object $existingRow = null): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku') || ! Schema::hasColumn($table, 'item_id')) {
            return;
        }

        $sku = trim($sku);
        if ($sku === '' || $itemId === '') {
            return;
        }

        try {
            if ($existingRow && isset($existingRow->sku)) {
                DB::table($table)->where('sku', $existingRow->sku)->update([
                    'item_id' => $itemId,
                    'updated_at' => now(),
                ]);

                return;
            }

            $found = DB::table($table)
                ->where('sku', $sku)
                ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                ->first();

            if ($found) {
                DB::table($table)->where('id', $found->id)->update([
                    'item_id' => $itemId,
                    'updated_at' => now(),
                ]);

                return;
            }

            $insert = ['sku' => $sku, 'item_id' => $itemId];
            if (Schema::hasColumn($table, 'created_at')) {
                $insert['created_at'] = now();
            }
            if (Schema::hasColumn($table, 'updated_at')) {
                $insert['updated_at'] = now();
            }
            DB::table($table)->insert($insert);
        } catch (\Throwable) {
            // Non-fatal: push can still proceed with resolved item_id.
        }
    }
}
