<?php

namespace App\Services\Veeqo;

use App\Http\Controllers\Channels\VeeqoChargebackController;
use App\Http\Controllers\PurchaseMaster\CategoryController;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Copies the SKU report's "Avg Chargeback Total" (12-month chargeback amount ÷ units sold) into
 * Shipping Master O-Size Charge as a positive figure. Saving goes through updateDimWtMaster so
 * Ship / Temu ship / Ship BB totals, combos and history update exactly as a manual edit does.
 */
class ChargebackOsizeSync
{
    /**
     * @return array{rows:int, updated:int, unchanged:int, missing:int, failed:int, updated_skus:array<int,string>}
     */
    public function run(?callable $log = null): array
    {
        $report = app(VeeqoChargebackController::class)->skuReportData(Request::create('/', 'GET', [
            'types' => implode('|', VeeqoChargebackController::CHARGEBACK_TYPES),
            'direction' => 'debit',
        ]));

        $figures = [];
        foreach ($report['rows'] ?? [] as $row) {
            if ($row['per_unit'] === null || $row['sku'] === '(no SKU found)') {
                continue;
            }
            $figures[strtoupper(trim($row['sku']))] = number_format(abs((float) $row['per_unit']), 2, '.', '');
        }

        $stats = ['rows' => count($figures), 'updated' => 0, 'unchanged' => 0, 'missing' => 0, 'failed' => 0, 'updated_skus' => []];
        if ($figures === []) {
            return $stats;
        }

        $products = ProductMaster::query()
            ->whereIn(DB::raw('UPPER(TRIM(sku))'), array_keys($figures))
            ->get(['id', 'sku', 'Values'])
            ->keyBy(fn ($p) => strtoupper(trim((string) $p->sku)));

        $controller = app(CategoryController::class);
        foreach ($figures as $key => $value) {
            $product = $products[$key] ?? null;
            if (! $product) {
                $stats['missing']++;
                continue;
            }
            $values = is_array($product->Values) ? $product->Values : (json_decode((string) $product->Values, true) ?: []);
            $current = trim((string) ($values['o_size_charge'] ?? ''));
            if ($current !== '' && is_numeric($current) && abs((float) $current - (float) $value) < 0.005) {
                $stats['unchanged']++;
                continue;
            }

            try {
                $response = $controller->updateDimWtMaster(Request::create('/', 'POST', [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'o_size_charge' => $value,
                ]));
                $ok = (bool) ($response->getData(true)['success'] ?? false);
            } catch (\Throwable $e) {
                Log::warning('Chargeback O-Size sync failed for '.$product->sku.': '.$e->getMessage());
                $ok = false;
            }

            if ($ok) {
                $stats['updated']++;
                $stats['updated_skus'][] = $product->sku;
                if ($log) {
                    $log($product->sku.': O-Size Charge '.($current === '' ? '(blank)' : $current).' → '.$value);
                }
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }
}
