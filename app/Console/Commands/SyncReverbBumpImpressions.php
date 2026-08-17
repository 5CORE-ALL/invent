<?php

namespace App\Console\Commands;

use App\Models\ReverbProduct;
use App\Services\ReverbApiService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SyncReverbBumpImpressions extends Command
{
    protected $signature = 'reverb:sync-bump-impressions
        {--limit= : Max listings to refresh}';

    protected $description = 'Write Reverb bump ads (bid, recommended bid, impressions, interactions) from GET /listings/{id}/bump';

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
        $this->info("Refreshing bump ads for {$total} listing(s)...");

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/hal+json',
            'Accept-Version' => '3.0',
        ];

        $updated = 0;
        $index = 0;
        $hasApiRecommendedBid = Schema::hasColumn('reverb_products', 'api_recommended_bid');
        $hasTotalInteractions = Schema::hasColumn('reverb_products', 'total_interactions');
        $l30Baselines = ReverbApiService::l30InteractionBaselines();
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
            $ads = ReverbApiService::parseListingBumpAds($data);
            $payload = [
                'views' => $ads['views'],
                'updated_at' => now(),
            ];
            if ($ads['bump_bid'] !== null && $ads['bump_bid'] !== '') {
                $payload['bump_bid'] = $ads['bump_bid'];
            }
            if ($hasApiRecommendedBid) {
                $payload['api_recommended_bid'] = $ads['api_recommended_bid'];
            }
            if ($hasTotalInteractions) {
                $payload['total_interactions'] = ReverbApiService::l30InteractionsForSku(
                    (string) $row->sku,
                    $ads['views'],
                    $l30Baselines
                );
            }

            ReverbProduct::query()->where('id', $row->id)->update($payload);
            $updated++;
            usleep(150000);
        }

        $this->info("Wrote bump ads for {$updated} listing(s).");

        return self::SUCCESS;
    }
}
