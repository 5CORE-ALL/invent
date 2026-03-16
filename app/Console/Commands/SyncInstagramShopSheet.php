<?php

namespace App\Console\Commands;

use App\Http\Controllers\ApiController;
use App\Models\InstagramShopSheetdata;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncInstagramShopSheet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-instagram-shop-sheet';

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
        $url = 'https://script.google.com/macros/s/AKfycbx0LEF-LnKysW5FfTmmfPb2TQqjCblcabCqrAuKHuWhAa8-Z6w9M0sPj2y_TcrEBdoL/exec'; // your Google Apps Script endpoint

        $this->info('Fetching data from Google Sheet...');

        try {
            $response = Http::get($url);

            if ($response->failed()) {
                $this->error('Failed to fetch data from Google Sheet.');
                return;
            }

            $payload = $response->json();

            if (!isset($payload['data']) || !is_array($payload['data'])) {
                $this->error('Invalid response structure from Google Sheet.');
                return;
            }

            $data = $payload['data'];
            $savedCount = 0;

            foreach ($data as $item) {
                if (empty($item['sku'])) {
                    $this->warn("Skipped row because 'sku' key was missing or empty.");
                    continue;
                }

                // Convert blank ("") → null
                $i_l30 = isset($item['i_l30']) && $item['i_l30'] !== '' ? (int)$item['i_l30'] : null;
                $i_l60 = isset($item['i_l60']) && $item['i_l60'] !== '' ? (int)$item['i_l60'] : null;
                $price = isset($item['price']) && $item['price'] !== '' ? (float)$item['price'] : null;
                $views = isset($item['views']) && $item['views'] !== '' ? (int)$item['views'] : null;

                InstagramShopSheetdata::updateOrCreate(
                    ['sku' => $item['sku']],
                    [
                        'i_l30' => $i_l30,
                        'i_l60' => $i_l60,
                        'price' => $price,
                        'views' => $views,
                    ]
                );

                $savedCount++;
            }

            $this->info("Instagram Shop Sheet data synced successfully! Total records saved: {$savedCount}");
        } catch (\Exception $e) {
            Log::error('Error fetching Instagram sheet data: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
        }
    }



}
