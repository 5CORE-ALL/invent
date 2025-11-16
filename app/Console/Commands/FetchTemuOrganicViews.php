<?php

namespace App\Console\Commands;

use App\Models\TemuMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchTemuOrganicViews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-temu-organic-views';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
     public function handle()
    {
        $this->info("Starting Temu Sheet sync...");

        // Replace with your actual Google Apps Script URL
        $url = 'https://script.google.com/macros/s/AKfycbznrr9RkqooL3b0pzyDmV0aE0PufD0TX9tlsvZwSaoQaIYbSOEZ1XaIUoefzYUL_pFikg/exec?sheet=Temu';

        try {
            $response = Http::timeout(120)->get($url);

            if (!$response->successful()) {
                $this->error("Failed to fetch data. HTTP " . $response->status());
                return;
            }

            $data = $response->json();

            if (!is_array($data) || empty($data)) {
                $this->warn("No valid data found in Temu sheet.");
                return;
            }

            $saved = 0;
            foreach ($data as $row) {
                if (empty($row['sku'])) {
                    $this->warn("Skipped row with missing SKU.");
                    continue;
                }

                TemuMetric::updateOrCreate(
                    ['sku' => trim($row['sku'])],
                    [
                        // 'parent' => $row['parent'] ?? null,
                        'organic_views' => intval($row['organic_views'] ?? 0),
                        'ad_sold' => intval($row['ad_sold'] ?? 0),
                    ]
                );

                $saved++;
            }

            $this->info("Temu Sheet data synced successfully! Total records saved/updated: {$saved}");

        } catch (\Exception $e) {
            $this->error("Error syncing Temu Sheet: " . $e->getMessage());
        }
    }
}
