<?php

namespace App\Console\Commands;

use App\Models\TiktokAdsConnection;
use App\Services\TikTokAdsApiService;
use Illuminate\Console\Command;

class TikTokAdsCheckCommand extends Command
{
    protected $signature = 'tiktok-ads:check';

    protected $description = 'Verify TikTok Marketing API reachability (proxy / India block), app credentials and stored advertiser tokens';

    public function handle(TikTokAdsApiService $ads): int
    {
        if (! $ads->isConfigured()) {
            $this->error('TIKTOK_ADS_APP_ID / TIKTOK_ADS_APP_SECRET are not set.');

            return self::FAILURE;
        }
        $this->line('Proxy: '.(config('services.tiktok_ads.proxy') ? 'configured' : 'none (direct)'));

        // A dummy auth_code: a reachable API with valid app credentials answers "invalid auth_code";
        // a wrong secret answers with an app/secret error; a blocked IP never reaches JSON.
        try {
            $ads->call('POST', '/open_api/v1.3/oauth2/access_token/', [
                'app_id' => config('services.tiktok_ads.app_id'),
                'secret' => config('services.tiktok_ads.app_secret'),
                'auth_code' => 'connectivity_check',
            ]);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'non-JSON') || str_contains($msg, 'request failed')) {
                $this->error('API not reachable: '.$msg);

                return self::FAILURE;
            }
            $this->info('API reachable. TikTok replied: '.$msg);
            $this->line('(An "auth_code" error here means the app id + secret were accepted; an app/secret error means they are wrong.)');
        }

        $connections = TiktokAdsConnection::all();
        if ($connections->isEmpty()) {
            $this->warn('No advertiser authorized yet. Open /tiktok-ads/connect while logged in.');

            return self::SUCCESS;
        }
        foreach ($connections as $c) {
            try {
                $info = $ads->advertiserInfo([$c->advertiser_id], $c->access_token);
                $this->info("Advertiser {$c->advertiser_id}: token OK ".json_encode($info[$c->advertiser_id] ?? []));
            } catch (\Throwable $e) {
                $this->error("Advertiser {$c->advertiser_id}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
