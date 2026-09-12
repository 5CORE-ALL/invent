<?php

namespace App\Console\Commands;

use App\Http\Controllers\ShopifyRawDataController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class GenerateMovementAnalysis extends Command
{
    protected $signature = 'movement:generate';

    protected $description = 'Generate movement_analysis from /shopify sales (shopify_raw_orders)';

    public function handle()
    {
        try {
            try {
                DB::connection()->getPdo();
                $this->info("✓ Database connection OK");
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
            }

            if (! Schema::hasTable('shopify_raw_orders')) {
                $this->error('✗ shopify_raw_orders table missing.');
                return 1;
            }

            // Same Pacific calendar as /shopify; last 12 months inclusive of current month.
            $now = Carbon::now('America/Los_Angeles');
            $startDate = $now->copy()->subMonths(11)->startOfMonth();
            $endDate = $now->copy()->endOfMonth();
            $start = $startDate->toDateString();
            $end = $endDate->toDateString();

            $orderQuery = DB::table('shopify_raw_orders')
                ->selectRaw('
                    DATE_FORMAT(order_date, "%b") as month,
                    sku,
                    SUM(quantity) as total_qty
                ')
                ->whereBetween('order_date', [$start, $end])
                ->whereNotNull('sku')
                ->where('sku', '!=', '');
            ShopifyRawDataController::applyDirectExclusions($orderQuery);
            $orderData = $orderQuery
                ->groupBy('month', 'sku')
                ->orderBy('sku')
                ->get();

            $monthlyOrderQuery = DB::table('shopify_raw_orders')
                ->selectRaw('
                    sku,
                    YEAR(order_date) as year,
                    MONTH(order_date) as month,
                    SUM(quantity) as order_count
                ')
                ->whereBetween('order_date', [$start, $end])
                ->whereNotNull('sku')
                ->where('sku', '!=', '');
            ShopifyRawDataController::applyDirectExclusions($monthlyOrderQuery);
            $monthlyOrderData = $monthlyOrderQuery
                ->groupBy('sku', 'year', 'month')
                ->orderBy('sku')
                ->orderBy('year')
                ->orderBy('month')
                ->get();

            if ($orderData->isEmpty()) {
                $this->warn('⚠️ No data found in shopify_raw_orders for the given range.');
                DB::connection()->disconnect();
                return 0;
            }

            $grouped = [];
            foreach ($orderData as $row) {
                $sku = $row->sku ?? '';
                if (empty($sku)) {
                    continue;
                }
                
                if (!isset($grouped[$sku])) {
                    $grouped[$sku] = [
                        "Jan" => 0, "Feb" => 0, "Mar" => 0, "Apr" => 0,
                        "May" => 0, "Jun" => 0, "Jul" => 0, "Aug" => 0,
                        "Sep" => 0, "Oct" => 0, "Nov" => 0, "Dec" => 0,
                    ];
                }

                $monthName = ucfirst(strtolower($row->month ?? ''));
                if (!empty($monthName) && isset($grouped[$sku][$monthName])) {
                    $grouped[$sku][$monthName] += $row->total_qty ?? 0;
                }
            }

            if (empty($grouped)) {
                $this->warn('⚠️ No valid SKU data to process.');
                DB::connection()->disconnect();
                return 0;
            }

            $this->restoreAutoIncrement('movement_analysis');
            if (Schema::hasTable('sku_monthly_orders')) {
                $this->restoreAutoIncrement('sku_monthly_orders');
            }

            $movementNeedsId = $this->needsExplicitId('movement_analysis');
            $nextMovementId = $movementNeedsId
                ? ((int) DB::table('movement_analysis')->max('id') + 1)
                : null;

            $chunks = array_chunk($grouped, 100, true);
            foreach ($chunks as $chunk) {
                foreach ($chunk as $sku => $months) {
                    $values = [
                        'months' => json_encode($months),
                        'updated_at' => now(),
                    ];
                    $updated = DB::table('movement_analysis')->where('sku', $sku)->update($values);
                    if ($updated === 0 && ! DB::table('movement_analysis')->where('sku', $sku)->exists()) {
                        $values['sku'] = $sku;
                        $values['created_at'] = now();
                        if ($movementNeedsId) {
                            $values['id'] = $nextMovementId++;
                        }
                        DB::table('movement_analysis')->insert($values);
                    }
                }
            }

            // Populate sku_monthly_orders: one row per (sku, year, month) with order count
            if (Schema::hasTable('sku_monthly_orders') && $monthlyOrderData->isNotEmpty()) {
                $skuNeedsId = $this->needsExplicitId('sku_monthly_orders');
                $nextSkuId = $skuNeedsId
                    ? ((int) DB::table('sku_monthly_orders')->max('id') + 1)
                    : null;

                foreach ($monthlyOrderData as $row) {
                    $sku = $row->sku ?? '';
                    if (empty($sku)) continue;
                    $sku = strtoupper(trim($sku));
                    $keys = [
                        'sku' => $sku,
                        'year' => (int) $row->year,
                        'month' => (int) $row->month,
                    ];
                    $values = [
                        'order_count' => (int) ($row->order_count ?? 0),
                        'updated_at' => now(),
                    ];
                    $updated = DB::table('sku_monthly_orders')->where($keys)->update($values);
                    if ($updated === 0 && ! DB::table('sku_monthly_orders')->where($keys)->exists()) {
                        $values = array_merge($keys, $values, ['created_at' => now()]);
                        if ($skuNeedsId) {
                            $values['id'] = $nextSkuId++;
                        }
                        DB::table('sku_monthly_orders')->insert($values);
                    }
                }
                $this->info('✅ sku_monthly_orders updated: ' . $monthlyOrderData->count() . ' rows.');
            }

            $this->info('✅ movement_analysis data generated successfully for ' . count($grouped) . ' SKUs.');
            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::connection()->disconnect();
        }
    }

    private function restoreAutoIncrement(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
            return;
        }

        try {
            $col = collect(DB::select('SHOW COLUMNS FROM '.$table.' WHERE Field = ?', ['id']))->first();
            $extra = strtolower((string) ($col->Extra ?? ''));
            if (str_contains($extra, 'auto_increment')) {
                return;
            }

            $type = strtoupper((string) ($col->Type ?? 'bigint unsigned'));
            if (! str_contains(strtolower($type), 'int')) {
                $type = 'BIGINT UNSIGNED';
            }

            $next = (int) DB::table($table)->max('id') + 1;
            DB::statement("ALTER TABLE `{$table}` MODIFY `id` {$type} NOT NULL AUTO_INCREMENT");
            DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$next}");
            $this->info("Restored AUTO_INCREMENT on {$table}.id (next = {$next}).");
        } catch (\Throwable $e) {
            $this->warn("Could not restore AUTO_INCREMENT on {$table}.id: ".$e->getMessage());
        }
    }

    private function needsExplicitId(string $table): bool
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
            return false;
        }

        try {
            $col = collect(DB::select('SHOW COLUMNS FROM '.$table.' WHERE Field = ?', ['id']))->first();
            $extra = strtolower((string) ($col->Extra ?? ''));

            return ! str_contains($extra, 'auto_increment');
        } catch (\Throwable $e) {
            return true;
        }
    }
}
