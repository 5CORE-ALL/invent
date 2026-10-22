<?php

namespace App\Console\Commands;

use App\Http\Controllers\Campaigns\GoogleShoppingCampaignsController;
use App\Services\GoogleShoppingLiveBidBgtService;
use Illuminate\Console\Command;

/**
 * Reads today's average CPC (spend ÷ clicks so far today) for every ENABLED Shopping campaign
 * straight from the Google Ads API and stores it for the LT CPC column. Read-only against Google;
 * the shopping page never starts this.
 */
class SyncGoogleShoppingLiveCpcCommand extends Command
{
    protected $signature = 'google-shopping:sync-live-cpc {--campaign= : Only this campaign id}';

    protected $description = 'Pull live (today) CPC from Google Ads for the Shopping LT CPC column.';

    public function handle(GoogleShoppingLiveBidBgtService $service): int
    {
        @ini_set('memory_limit', '512M');
        $customerId = str_replace('-', '', trim((string) config('services.google_ads.login_customer_id')));
        $ids = app(GoogleShoppingCampaignsController::class)->shoppingEnabledCampaignIds();
        $only = preg_replace('/\D+/', '', (string) $this->option('campaign')) ?? '';
        if ($only !== '') {
            $ids = array_values(array_filter($ids, static fn (string $id): bool => $id === $only));
        }
        if ($ids === []) {
            $this->warn('No enabled Shopping campaigns to pull.');

            return self::SUCCESS;
        }

        $result = $service->pullAndStoreLiveCpc($customerId, $ids, 'shopping');
        if (! $result['ok']) {
            $this->error('Live CPC pull failed: '.($result['error'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $this->info('Live CPC stored for '.count($ids).' Shopping campaigns ('.$result['with_clicks'].' with clicks today).');

        return self::SUCCESS;
    }
}
