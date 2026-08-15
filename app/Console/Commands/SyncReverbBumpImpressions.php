<?php

namespace App\Console\Commands;

use App\Models\ReverbProduct;
use App\Services\ReverbApiService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SyncReverbBumpImpressions extends Command
{
    protected $signature = 'reverb:sync-bump-impressions
        {--limit= : Max listings to refresh}';

    protected $description = 'Write Reverb bump impressions (GET /listings/{id}/bump) into reverb_products.views for the /reverb-pricing Views column';

    public function handle(): int
    {
        $token = ReverbApiService::getReverbBearerToken();
        if (! $token) {
            $this->error('Reverb API token not configured.');

            return self::FAILURE;
        }

        $query = ReverbProduct::query()
            ->whereNotNull('reverb_listing_id')
            ->where('reverb_listing_id', '!=', '')
            ->orderBy('id');
        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $rows = $query->get(['id', 'sku', 'reverb_listing_id']);
        $total = $rows->count();
        $this->info("Refreshing bump impressions for {$total} listing(s)...");

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/hal+json',
            'Accept-Version' => '3.0',
        ];

        $updated = 0;
        $index = 0;
        foreach ($rows as $row) {
            $index++;
            $listingId = trim((string) $row->reverb_listing_id);
            if ($listingId === '') {
                continue;
            }
            if ($index % 50 === 0) {
                $this->info("  {$index}/{$total} ({$updated} updated)...");
            }

            try {
                $response = Http::withHeaders($headers)
                    ->timeout(30)
                    ->retry(3, 2000, throw: false)
                    ->get('https://api.reverb.com/api/listings/'.$listingId.'/bump');
            } catch (ConnectionException $e) {
                $this->warn("  Connection reset at {$index}/{$total} (listing {$listingId}).");
                usleep(500000);
                continue;
            } catch (\Throwable $e) {
                $this->warn("  Failed listing {$listingId}: ".$e->getMessage());
                continue;
            }

            if ($response->failed()) {
                continue;
            }

            $data = $response->json() ?? [];
            $raw = $data['bump_v2_stats']['impressions'] ?? 0;
            $impressions = is_numeric($raw) && (int) $raw > 0 ? (int) $raw : 0;

            ReverbProduct::query()->where('id', $row->id)->update([
                'views' => $impressions,
                'updated_at' => now(),
            ]);
            $updated++;
            usleep(150000);
        }

        $this->info("Wrote bump impressions to Views for {$updated} listing(s).");

        return self::SUCCESS;
    }
}
