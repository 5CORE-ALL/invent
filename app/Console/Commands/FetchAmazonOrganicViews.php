<?php

namespace App\Console\Commands;

use App\Models\AmazonDatasheet;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchAmazonOrganicViews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-amazon-organic-views';

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
        $this->info('Starting Amazon sheet sync...');

        // Replace with your Google Apps Script Web App URL
        $url = 'https://script.google.com/macros/s/AKfycbzYSjDF6VgbmvrgdhSzCwSGfBGvFD2gWJJgNYMiIVXwamP7WXQPzwMGBEo2eYRzIIS_FA/exec';

        try {
            $response = Http::timeout(120)->get($url);

            if (!$response->successful()) {
                $this->error('Failed to fetch data from Google Sheet: ' . $response->status());
                return Command::FAILURE;
            }

            $rows = $response->json();
            $saved = 0;

            if (empty($rows)) {
                $this->warn('No data found in the sheet.');
                return Command::SUCCESS;
            }

            foreach ($rows as $row) {
                if (empty($row['asin'])) {
                    $this->warn('Skipped a row with missing ASIN.');
                    continue;
                }

                AmazonDatasheet::updateOrCreate(
                    ['asin' => trim($row['asin'])],
                    [
                        'organic_views' => (int) ($row['clicks'] ?? 0),
                        'sold' => (int) ($row['purchases'] ?? 0),
                        'updated_at' => Carbon::now(),
                    ]
                );

                $saved++;
            }

            $this->info("Amazon Sheet data synced successfully! Total records saved/updated: {$saved}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            Log::error('Error syncing Amazon sheet data:', ['error' => $e->getMessage()]);
            $this->error('Exception occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

}
