<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Http\Controllers\ApiController;
use App\Models\ShopifySku;
use App\Services\CronMonitor\CronExecutionContext;
use Illuminate\Console\Command;

class SyncShopifyQuantity extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'sync:shopify-quantity
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Sync Shopify Quantity';

    protected string $monitorJobName = 'Sync Shopify Quantity';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeSync($m),
            $this->monitorJobName
        );
    }

    protected function executeSync(CronExecutionContext $monitor): int
    {
        $chunkSize = $this->monitoredChunkSize();
        $controller = new ApiController();
        $sheet = $controller->fetchShopifyB2CListingData();
        $monitor->markApiConnected();
        $rows = collect($sheet->getData()->data ?? [])->values()->all();

        $monitor->setFetched(count($rows));
        $monitor->setExpected(count($rows));

        $this->chunkProcessor()->process(
            $monitor,
            $rows,
            function (array $chunk) {
                $updated = 0;
                $skipped = 0;
                foreach ($chunk as $row) {
                    $sku = trim($row->{'(Child) sku'} ?? '');
                    if (!$sku) {
                        $skipped++;
                        continue;
                    }

                    ShopifySku::updateOrCreate(
                        ['sku' => $sku],
                        [
                            'shopify_l30' => $this->toDecimalOrNull($row->{'SH L30'} ?? null),
                        ]
                    );
                    $updated++;
                }

                return [
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'failed' => 0,
                    'processed' => count($chunk),
                ];
            },
            $chunkSize,
            null,
            ['transaction' => true]
        );

        $this->info('Shopify sheet synced successfully!');

        return self::SUCCESS;
    }

    private function toDecimalOrNull($value)
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function toIntOrNull($value)
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
