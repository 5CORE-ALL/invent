<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\ProductMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncCpMasterToSheet extends Command
{
    use ProcessesUpdatesInChunks;

    protected $signature = 'app:sync-cp-master-to-sheet {--chunk= : Override sheet push batch size (default from cron-monitor config)}';
    protected $description = 'Sync CP Master data to Google Sheet via Web App';

    public function handle()
    {
        $sheetUrl = "https://script.google.com/macros/s/AKfycbwfegttlsmKh-6RKa9NXSJA6zLDidFqex0iGzqHTONt8Za3raj6WSHmGJflM98uOT-tUA/exec";   // ✅ Change

        $batchSize = $this->monitoredChunkSize();
        $total = ProductMaster::query()->count();
        $batches = $total > 0 ? (int) ceil($total / $batchSize) : 0;

        $this->info("✅ Starting sync: {$total} rows → {$batches} batches of {$batchSize} (chunkById)");

        $inserted = 0;
        $batchIndex = 0;

        ProductMaster::query()
            ->select('*', 'Values as values')
            ->orderBy('id')
            ->chunkById($batchSize, function ($chunk) use ($sheetUrl, $batches, &$inserted, &$batchIndex) {
                $batchIndex++;
                $formatted = [];

                foreach ($chunk as $row) {
                    $values = json_decode($row->values, true);

                    $formatted[] = [
                        "parent" => $row->parent,
                        "sku" => $row->sku,
                        "status" => $values['status'] ?? '',
                        "lp" => $values['lp'] ?? 0,
                        "cp" => $values['cp'] ?? 0,
                        "frght" => $values['frght'] ?? 0,
                        "ship" => $values['ship'] ?? 0,
                        "temu_ship" => $values['temu_ship'] ?? 0,
                        "ebay2_ship" => $values['ebay2_ship'] ?? 0,
                        "initial_quantity" => $values['initial_quantity'] ?? '',
                        "label_qty" => $values['label_qty'] ?? '',
                        "wt_act" => $values['wt_act'] ?? 0,
                        "wt_decl" => $values['wt_decl'] ?? 0,
                        "l" => $values['l'] ?? 0,
                        "w" => $values['w'] ?? 0,
                        "h" => $values['h'] ?? 0,
                        "cbm" => $values['cbm'] ?? 0,
                        "l2_url" => $values['l2_url'] ?? '',
                        "dc" => $values['dc'] ?? '',
                        "pcs_per_box" => $values['pcs_per_box'] ?? '',
                        "l1" => $values['l1'] ?? '',
                        "b" => $values['b'] ?? '',
                        "h1" => $values['h1'] ?? '',
                        "weight" => $values['weight'] ?? '',
                        "msrp" => $values['msrp'] ?? '',
                        "map" => $values['map'] ?? '',
                        "upc" => $values['upc'] ?? '',
                    ];
                }

                /* ✅ Log sample request for first batch */
                if ($batchIndex === 1) {
                    Log::info("✅ Sample SEND Payload", [
                        "sample" => array_slice($formatted, 0, 3)
                    ]);
                }

                try {

                    $response = Http::withHeaders([
                        "Content-Type" => "application/json"
                    ])->timeout(90)
                    ->post($sheetUrl, [
                        "data" => $formatted
                    ]);

                    Log::info("📥 Raw Batch Response", [
                        "batch"  => $batchIndex,
                        "status" => $response->status(),
                        "raw"    => $response->body()
                    ]);

                    $body = $response->json();

                    if (!$body) {
                        Log::error("❌ Invalid JSON Received", [
                            "batch" => $batchIndex,
                            "raw"   => $response->body()
                        ]);
                        $this->error("❌ Batch " . $batchIndex . " - Invalid JSON received");
                    }

                    if ($response->successful() && ($body['success'] ?? false)) {

                        $this->info("✅ Batch " . $batchIndex . " / $batches success - Updated: " . ($body["updated"] ?? 0) . ", Inserted: " . ($body["inserted"] ?? 0));
                        Log::info("✅ Batch " . $batchIndex . " / $batches success", [
                            "received" => $body["received"] ?? 0,
                            "updated" => $body["updated"] ?? 0,
                            "inserted" => $body["inserted"] ?? 0
                        ]);

                        $inserted += count($formatted);

                    } else {
                        $this->error("❌ Batch " . $batchIndex . " FAILED - Status: " . $response->status());
                        Log::error("❌ Batch " . $batchIndex . " FAILED", [
                            "status" => $response->status(),
                            "json"   => $body,
                            "raw"    => $response->body()
                        ]);
                    }

                    sleep(1);

                } catch (\Throwable $e) {
                    $this->error("❌ Batch " . $batchIndex . " Exception: " . $e->getMessage());
                    Log::error("❌ Batch Exception", [
                        "batch" => $batchIndex,
                        "msg" => $e->getMessage(),
                    ]);
                }
            });

        $this->info("✅ Final Result: {$inserted} / {$total} uploaded.");
        Log::info("✅ Sync Complete", [
            "total" => $total,
            "inserted" => $inserted,
            "batches" => $batches
        ]);
    }
}
