<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateMovementAnalysis extends Command
{
    protected $signature = 'movement:generate';

    protected $description = 'Generate and insert movement_analysis data from shopify_order_items';

    public function handle()
    {
        try {
            // Check database connection
            try {
                DB::connection()->getPdo();
                DB::connection('apicentral')->getPdo();
                $this->info("✓ Database connections OK");
            } catch (\Exception $e) {
                $this->error("✗ Database connection failed: " . $e->getMessage());
                return 1;
            }

            $now = Carbon::now();

            $currentYear = $now->year;
            $currentMonth = $now->month;

            if ($currentMonth >= 11) {
                $previousMonths = 12 - $currentMonth; 
            } else {
                $previousMonths = 2; 
            }

            $startDate = Carbon::create($currentYear - 1, 12 - $previousMonths + 1, 1)->startOfMonth();
            $endDate = $now->copy()->endOfMonth();

            $orderData = DB::connection('apicentral')->table('shopify_order_items')
                ->selectRaw('
                    DATE_FORMAT(order_date, "%b") as month,
                    sku,
                    SUM(quantity) as total_qty
                ')
                ->whereBetween('order_date', [$startDate, $endDate])
                ->groupBy('month', 'sku')
                ->orderBy('sku')
                ->get();
            
            DB::connection('apicentral')->disconnect();

            if ($orderData->isEmpty()) {
                $this->warn('⚠️ No data found in shopify_order_items for the given range.');
                DB::disconnect();
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
                DB::disconnect();
                return 0;
            }

            // Process in chunks to avoid too many connections
            $chunks = array_chunk($grouped, 100, true);
            foreach ($chunks as $chunk) {
                foreach ($chunk as $sku => $months) {
                    DB::table('movement_analysis')->updateOrInsert(
                        ['sku' => $sku],
                        [
                            'months' => json_encode($months),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
                DB::disconnect();
            }

            $this->info('✅ movement_analysis data generated successfully for ' . count($grouped) . ' SKUs.');
            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        } finally {
            DB::disconnect();
            try {
                DB::connection('apicentral')->disconnect();
            } catch (\Exception $e) {
                // Ignore disconnect errors
            }
        }
    }
}
