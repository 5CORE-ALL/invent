<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncWalmartMetrics extends Command
{
    use ProcessesUpdatesInChunks;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:walmart-metrics-data {--chunk= : Override DB write chunk size (default from cron-monitor config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Walmart metrics daily into inventory DB';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $chunkSize = $this->monitoredChunkSize();
        $synced = 0;

        // Stream from apicentral; write to mysql in transactions of config chunk size.
        // Use chunk() (not chunkById) — source table may not expose a reliable id column.
        DB::connection('apicentral')
            ->table('walmart_metrics')
            ->orderBy('sku')
            ->chunk($chunkSize, function ($rows) use (&$synced) {
                DB::connection('mysql')->transaction(function () use ($rows, &$synced) {
                    foreach ($rows as $row) {
                        DB::connection('mysql')->table('walmart_metrics')->updateOrInsert(
                            ['sku' => $row->sku], // match by sku
                            [
                                'l30' => $row->l30,
                                'l30_amt' => $row->l30_amt,
                                'l60' => $row->l60,
                                'l60_amt' => $row->l60_amt,
                                'price' => $row->price,
                                'stock' => $row->stock,
                                'updated_at' => now(),
                            ]
                        );
                        $synced++;
                    }
                });
            });

        $this->info("Walmart metrics synced successfully! ({$synced} row(s))");
    }
}
