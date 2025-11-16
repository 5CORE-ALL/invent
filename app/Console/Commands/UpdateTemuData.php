<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateTemuData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-temu-data {--include-api-data : Include additional SKUs from API data that are not in temu_data.txt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update temu_metrics table with quantities from temu_data.txt and optionally from API data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Read the provided data
        $data = file_get_contents('temu_data.txt');

        // Parse the tab-separated data
        $lines = explode("\n", trim($data));
        $providedData = [];
        $totalProvided = 0;

        foreach ($lines as $line) {
            if (empty(trim($line)) || strpos($line, 'contribution sku') === 0) continue;

            // Split by tab - expect 3 columns: SKU, ID, quantity
            $parts = explode("\t", $line);
            if (count($parts) >= 3) {
                $sku = trim($parts[0]);
                $quantityStr = trim($parts[2]); // Use third column for quantity

                // Remove quotes and convert to int
                $quantityStr = str_replace('"', '', $quantityStr);
                $quantity = (int)$quantityStr;

                // Clean up SKU if needed
                $sku = str_replace('"', '', $sku);

                if (!empty($sku) && $quantity > 0) {
                    // Sum the actual quantities from the file
                    $providedData[$sku] = ($providedData[$sku] ?? 0) + $quantity; // Sum quantities
                    $totalProvided += $quantity; // Sum total quantities
                }
            }
        }

        $this->info('Starting update process...');
        $this->info('Total SKUs to process: ' . count($providedData));
        $this->info('Total quantity to update: ' . $totalProvided);

        // If --include-api-data flag is set, add SKUs from database that aren't in the file
        if ($this->option('include-api-data')) {
            $this->info('Including additional SKUs from API data...');

            // Get SKUs from database that have quantity but aren't in the file
            $apiSkus = DB::table('temu_metrics')
                ->where('quantity_purchased_l30', '>', 0)
                ->whereNotIn('sku', array_keys($providedData))
                ->pluck('quantity_purchased_l30', 'sku')
                ->toArray();

            foreach ($apiSkus as $sku => $quantity) {
                $providedData[$sku] = $quantity;
                $totalProvided += $quantity;
                $this->line("Added from API: {$sku} -> {$quantity}");
            }

            $this->info('Added ' . count($apiSkus) . ' additional SKUs from API data');
            $this->info('New total SKUs: ' . count($providedData));
            $this->info('New total quantity: ' . $totalProvided);
        }

        $this->line('');

        $updated = 0;
        $inserted = 0;
        $skipped = 0;

        foreach ($providedData as $sku => $quantity) {
            // Check if SKU exists
            $existing = DB::table('temu_metrics')
                ->where('sku', $sku)
                ->first();

            if ($existing) {
                // Update the quantity
                $oldQuantity = $existing->quantity_purchased_l30;
                DB::table('temu_metrics')
                    ->where('sku', $sku)
                    ->update([
                        'quantity_purchased_l30' => $quantity,
                        'updated_at' => now()
                    ]);

                $updated++;
                $this->line("Updated: {$sku} -> {$quantity} (was {$oldQuantity})");
            } else {
                // Insert new SKU
                DB::table('temu_metrics')->insert([
                    'sku' => $sku,
                    'quantity_purchased_l30' => $quantity,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $inserted++;
                $this->line("Inserted: {$sku} -> {$quantity}");
            }
        }

        $this->line('');
        $this->info('=== UPDATE SUMMARY ===');
        $this->info("SKUs updated: {$updated}");
        $this->info("SKUs inserted: {$inserted}");
        $this->info("SKUs skipped (already had data): {$skipped}");
        $this->info("Total SKUs processed: " . ($updated + $inserted + $skipped));

        return 0;
    }
}