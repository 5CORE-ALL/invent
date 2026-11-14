<?php

namespace App\Services\Veeqo;

use App\Http\Controllers\Channels\VeeqoChargebackController;
use App\Http\Controllers\PurchaseMaster\CategoryController;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Copies the report's parent-wise "Avg Chargeback Total" (12-month chargeback amount ÷ units sold, per parent) into
 * Shipping Master O-Size Charge of every SKU under that parent, as a positive figure. Saving goes through updateDimWtMaster so
 * Ship / Temu ship / Ship BB totals, combos and history update exactly as a manual edit does.
 */
class ChargebackOsizeSync
{
    public const LOCK = 'veeqo_chargeback_osize';

    /**
     * Run right after new chargeback data arrives (Amazon / TikTok sync, CSV import, SKU lookup), so
     * Shipping Master follows the report without anyone pressing the button. Skips when another fill is
     * already running and never throws, so it cannot fail the job that called it.
     *
     * @return array{rows:int, updated:int, unchanged:int, missing:int, failed:int, updated_skus:array<int,string>}|null
     */
    public function runAfterNewData(?callable $log = null): ?array
    {
        $lock = Cache::lock(self::LOCK, 600);
        if (! $lock->get()) {
            return null;
        }
        try {
            @set_time_limit(600);

            return $this->run($log);
        } catch (\Throwable $e) {
            Log::warning('Chargeback O-Size auto sync failed: '.$e->getMessage());

            return null;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{rows:int, updated:int, unchanged:int, missing:int, failed:int, updated_skus:array<int,string>}|null  $r
     */
    public static function summary(?array $r): string
    {
        if ($r === null) {
            return 'Shipping Master O-Size Charge: skipped (already running or failed, see log).';
        }

        return "Shipping Master O-Size Charge: updated {$r['updated']} SKUs, {$r['unchanged']} already up to date"
            .($r['missing'] ? ", {$r['missing']} not found in Shipping Master" : '')
            .($r['failed'] ? ", {$r['failed']} failed (see log)" : '').'.';
    }

    /**
     * Parent-wise: every SKU under a parent gets that parent's "Avg Chargeback Total" (12-month chargeback
     * amount for the whole parent ÷ units sold across all its SKUs). A SKU with no parent stands alone and
     * gets its own figure, exactly as the report's parent view groups it.
     *
     * @return array{rows:int, updated:int, unchanged:int, missing:int, failed:int, updated_skus:array<int,string>}
     */
    public function run(?callable $log = null): array
    {
        $report = app(VeeqoChargebackController::class)->skuReportData(Request::create('/', 'GET', [
            'types' => implode('|', VeeqoChargebackController::CHARGEBACK_TYPES),
            'direction' => 'debit',
        ]));

        // Parent (uppercase, as the report keys it) => positive per-unit figure.
        $figures = [];
        foreach ($report['parent_rows'] ?? [] as $row) {
            $key = strtoupper(trim((string) $row['parent']));
            if ($row['per_unit'] === null || $key === '' || $key === '(NO SKU FOUND)') {
                continue;
            }
            $figures[$key] = number_format(abs((float) $row['per_unit']), 2, '.', '');
        }

        $stats = ['rows' => count($figures), 'updated' => 0, 'unchanged' => 0, 'missing' => 0, 'failed' => 0, 'updated_skus' => []];
        if ($figures === []) {
            return $stats;
        }

        // Product Master SKUs grouped by normalized parent (same normalization the report uses).
        $idsByParent = [];
        DB::table('product_master')
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('parent')->where('parent', '!=', '')
            ->orderBy('id')
            ->get(['id', 'parent'])
            ->each(function ($r) use (&$idsByParent) {
                $p = strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) $r->parent)));
                if ($p !== '') {
                    $idsByParent[$p][] = (int) $r->id;
                }
            });

        // Product id => figure. Standalone SKUs (no parent group of that name) match on their own SKU.
        $valueById = [];
        $standalone = [];
        foreach ($figures as $parent => $value) {
            if (isset($idsByParent[$parent])) {
                foreach ($idsByParent[$parent] as $id) {
                    $valueById[$id] = $value;
                }
            } else {
                $standalone[$parent] = $value;
            }
        }
        if ($standalone !== []) {
            $bySku = ProductMaster::query()
                ->whereIn(DB::raw('UPPER(TRIM(sku))'), array_keys($standalone))
                ->get(['id', 'sku']);
            $found = [];
            foreach ($bySku as $p) {
                $k = strtoupper(trim((string) $p->sku));
                $valueById[(int) $p->id] = $standalone[$k];
                $found[$k] = true;
            }
            $stats['missing'] = count(array_diff_key($standalone, $found));
        }
        if ($valueById === []) {
            return $stats;
        }

        $controller = app(CategoryController::class);
        foreach (array_chunk(array_keys($valueById), 200) as $ids) {
            $products = ProductMaster::query()->whereIn('id', $ids)->get(['id', 'sku', 'Values']);
            foreach ($products as $product) {
                $value = $valueById[(int) $product->id];
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
        }

        return $stats;
    }
}