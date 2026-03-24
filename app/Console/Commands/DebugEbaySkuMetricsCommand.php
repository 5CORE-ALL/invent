<?php

namespace App\Console\Commands;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DebugEbaySkuMetricsCommand extends Command
{
    protected $signature = 'ebay:debug-sku {sku? : SKU to inspect (default: 138 RU)}';

    protected $description = 'Log why a SKU may be missing from ebay_metrics / ebay_2_metrics / ebay_3_metrics and whether it exists in product master.';

    public function handle(): int
    {
        $raw = $this->argument('sku') ?? '138 RU';
        $sku = str_replace("\u{00a0}", ' ', trim((string) $raw));
        $norm = strtolower(trim($sku));

        $this->info("Debugging eBay metrics for SKU: [{$sku}]");

        $pm = ProductMaster::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($sku) {
                $q->whereRaw('TRIM(sku) = ?', [trim($sku)])
                    ->orWhereRaw('LOWER(TRIM(sku)) = ?', [strtolower(trim($sku))]);
            })
            ->first();

        if ($pm) {
            $this->line('Product master: FOUND id='.$pm->id.' sku=['.$pm->sku.']');
        } else {
            $this->warn('Product master: NOT FOUND for exact/trim match — metrics jobs that join on SKU will skip this item.');
        }

        $channels = [
            'ebay_metrics' => [EbayMetric::class, 'ebay 1'],
            'ebay_2_metrics' => [Ebay2Metric::class, 'ebay 2'],
            'ebay_3_metrics' => [Ebay3Metric::class, 'ebay 3'],
        ];

        foreach ($channels as $table => [$modelClass, $label]) {
            if (! Schema::hasTable($table)) {
                $this->error("Table missing: {$table}");
                Log::channel('single')->warning('ebay:debug-sku table missing', ['sku' => $sku, 'table' => $table]);

                continue;
            }

            $row = $modelClass::query()->whereRaw('LOWER(TRIM(sku)) = ?', [$norm])->first();
            if ($row) {
                $this->line("{$label} ({$table}): FOUND id={$row->id}");
            } else {
                $this->warn("{$label} ({$table}): NOT FOUND");
            }
        }

        Log::info('ebay:debug-sku report', [
            'sku_input' => $raw,
            'sku_normalized' => $sku,
            'product_master_found' => (bool) $pm,
            'hints' => [
                'Inventory report pipeline populates ebay_metrics from app:fetch-ebay-reports (active listings with SKU).',
                'If SKU is missing on the live listing, eBay report rows may use NO-SKU-{item_id} or omit the row.',
                'Run: php artisan app:fetch-ebay-reports (eBay 1) after fixing listing SKU in Seller Hub.',
                'Listing title/status: php artisan ebay:fetch-listing-status',
            ],
        ]);

        $this->comment('Details written to default log channel as ebay:debug-sku report.');

        return self::SUCCESS;
    }
}
