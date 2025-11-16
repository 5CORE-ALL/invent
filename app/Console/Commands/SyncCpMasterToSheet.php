<?php

namespace App\Console\Commands;

use App\Models\ProductMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncCpMasterToSheet extends Command
{
    protected $signature = 'app:sync-cp-master-to-sheet';
    protected $description = 'Sync CP Master data to Google Sheet via Web App';

    public function handle()
    {
        $sheetUrl = "https://script.google.com/macros/s/AKfycbwfegttlsmKh-6RKa9NXSJA6zLDidFqex0iGzqHTONt8Za3raj6WSHmGJflM98uOT-tUA/exec";   // ✅ Change

        $rows = ProductMaster::select('*', 'Values as values')->get();
        $total = $rows->count();
        $batchSize = 400;
        $batches = ceil($total / $batchSize);

        Log::info("✅ Starting sync: {$total} rows → {$batches} batches");

        $inserted = 0;

        foreach ($rows->chunk($batchSize) as $index => $chunk) {

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
            if ($index === 0) {
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
                    "batch"  => $index + 1,
                    "status" => $response->status(),
                    "raw"    => $response->body()
                ]);

                $body = $response->json();

                if (!$body) {
                    Log::error("❌ Invalid JSON Received", [
                        "batch" => $index + 1,
                        "raw"   => $response->body()
                    ]);
                }

                if ($response->successful() && ($body['success'] ?? false)) {

                    Log::info("✅ Batch " . ($index + 1) . " / $batches success", [
                        "received" => $body["received"] ?? 0,
                        "updated" => $body["updated"] ?? 0,
                        "inserted" => $body["inserted"] ?? 0
                    ]);

                    $inserted += count($formatted);

                } else {
                    Log::error("❌ Batch " . ($index + 1) . " FAILED", [
                        "status" => $response->status(),
                        "json"   => $body,
                        "raw"    => $response->body()
                    ]);
                }

                sleep(1);

            } catch (\Throwable $e) {
                Log::error("❌ Batch Exception", [
                    "batch" => $index + 1,
                    "msg" => $e->getMessage(),
                ]);
            }
        }

        Log::info("✅ Final Result: {$inserted} / {$total} uploaded.");
    }
}
