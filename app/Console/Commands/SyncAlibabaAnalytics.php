<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\AlibabaAnalyticsController;
use App\Services\AlibabaApiService;
use Illuminate\Console\Command;

class SyncAlibabaAnalytics extends Command
{
    protected $signature = 'alibaba:sync-analytics';

    protected $description = 'Refresh /alibaba-analytics prices and stock from the Alibaba product API.';

    public function handle(AlibabaAnalyticsController $analytics, AlibabaApiService $api): int
    {
        @set_time_limit(0);

        $page = 1;
        $reset = true;

        while ($page <= 200) {
            $result = $analytics->syncApiPage($api, $page, $reset);
            $reset = false;
            $this->line($result['message'] ?? 'Page '.$page);

            if (empty($result['success'])) {
                return self::FAILURE;
            }
            if (! empty($result['done'])) {
                return self::SUCCESS;
            }

            $page++;
            usleep(150000);
        }

        $this->error('Stopped after 200 pages.');

        return self::FAILURE;
    }
}
