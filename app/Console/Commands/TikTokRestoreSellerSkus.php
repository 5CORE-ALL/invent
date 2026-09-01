<?php

namespace App\Console\Commands;

use App\Services\TikTok2ShopService;
use App\Services\TikTokShopService;
use Illuminate\Console\Command;

class TikTokRestoreSellerSkus extends Command
{
    protected $signature = 'tiktok:restore-seller-skus
        {--channel=tiktok : tiktok (shop 1) or tiktok2 (shop 2)}
        {--dry-run : List blank SKUs without writing to TikTok}';

    protected $description = 'Restore blank TikTok Seller Center SKUs from local tiktok_products tables';

    public function handle(): int
    {
        $channel = strtolower(trim((string) $this->option('channel')));
        if (! in_array($channel, ['tiktok', 'tiktok2'], true)) {
            $this->error('Invalid --channel. Use tiktok or tiktok2.');

            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        $label = $channel === 'tiktok2' ? 'TikTok 2' : 'TikTok';
        $configKey = $channel === 'tiktok2' ? 'tiktok2' : 'tiktok';
        $service = $channel === 'tiktok2' ? new TikTok2ShopService() : new TikTokShopService();

        $service->setOutputCallback(function ($type, $message) {
            match ($type) {
                'error' => $this->error($message),
                'warn' => $this->warn($message),
                default => $this->line($message),
            };
        });

        if (! $service->isAuthenticated()) {
            $accessToken = config("services.{$configKey}.access_token");
            $refreshToken = config("services.{$configKey}.refresh_token");
            if ($accessToken) {
                $service->setTokens((string) $accessToken, $refreshToken);
            }
        }

        if (! $service->isAuthenticated()) {
            $this->error("{$label} API: No access token found. Authenticate first.");

            return 1;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Restoring blank {$label} seller SKUs from local tables...");

        $result = $service->restoreBlankSellerSkus($dryRun);

        $this->info($result['message']);
        $this->table(
            ['Scanned', 'Blank', 'Restored', 'Skipped', 'Failed'],
            [[$result['scanned'], $result['blank'], $result['restored'], $result['skipped'], $result['failed']]]
        );

        return ($result['failed'] ?? 0) > 0 ? 1 : 0;
    }
}
