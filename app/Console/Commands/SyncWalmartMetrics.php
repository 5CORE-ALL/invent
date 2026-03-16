<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncWalmartMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:walmart-metrics-data';

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
        $data = DB::connection('apicentral')->table('walmart_metrics')->get();

        foreach ($data as $row) {
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
        }

        $this->info('Walmart metrics synced successfully!');
    }
}