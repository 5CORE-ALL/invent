<?php

namespace App\Console\Commands;

use App\Models\TiktokAdsConnection;
use App\Services\TikTokAdsApiService;
use Illuminate\Console\Command;

class TikTokAdsRequestCommand extends Command
{
    protected $signature = 'tiktok-ads:request
        {path : API path, e.g. /open_api/v1.3/campaign/get/}
        {--query=* : key=value pairs (JSON values allowed), e.g. --query=page_size=5 --query=fields=["campaign_id"]}
        {--advertiser= : Advertiser id (default: first authorized); also sent as advertiser_id}
        {--post : Use POST (query pairs become the JSON body)}';

    protected $description = 'Call any TikTok Marketing API endpoint with a stored token and print the data node (e.g. to try GMV Max endpoints)';

    public function handle(TikTokAdsApiService $ads): int
    {
        $conn = TiktokAdsConnection::query()
            ->when($this->option('advertiser'), fn ($q, $id) => $q->where('advertiser_id', $id))
            ->first();
        if (! $conn) {
            $this->error('No authorized advertiser. Open /tiktok-ads/connect first.');

            return self::FAILURE;
        }

        $params = ['advertiser_id' => $conn->advertiser_id];
        foreach ((array) $this->option('query') as $pair) {
            [$k, $v] = array_pad(explode('=', (string) $pair, 2), 2, '');
            $decoded = json_decode($v, true);
            $params[$k] = ($decoded !== null && json_last_error() === JSON_ERROR_NONE && ! $this->option('post'))
                ? $v
                : ($decoded ?? $v);
        }

        try {
            $data = $ads->call($this->option('post') ? 'POST' : 'GET', (string) $this->argument('path'), $params, $conn->access_token);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
