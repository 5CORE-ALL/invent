<?php

namespace App\Console\Commands;

use App\Services\Support\ChannelPushSpriceDailyEnqueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PushChannelBlueSpriceCommand extends Command
{
    protected $signature = 'channel:push-blue {channel : ebay1, ebay2, or ebay3}';

    protected $description = 'Queue eBay SKUs whose S PRC differs from the live listing, then start the push worker.';

    public const SCANNING_PREFIX = 'channel-push-blue-scanning-';

    public const RESULT_PREFIX = 'channel-push-blue-result-';

    public function handle(ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $channel = strtolower(trim((string) $this->argument('channel')));
        if (! in_array($channel, ['ebay1', 'ebay2', 'ebay3'], true)) {
            $this->error('Unsupported channel');

            return self::FAILURE;
        }

        $lock = Cache::lock('channel-push-blue-'.$channel, 1800);
        if (! $lock->get()) {
            $this->warn('Already scanning — skip');

            return self::SUCCESS;
        }

        Cache::put(self::SCANNING_PREFIX.$channel, true, now()->addMinutes(30));

        try {
            $res = $enqueue->enqueueChannel($channel);
            $message = (string) ($res['message'] ?? 'Done');
            Cache::put(self::RESULT_PREFIX.$channel, $message, now()->addMinutes(5));
            $this->info(strtoupper($channel).': '.$message);

            return self::SUCCESS;
        } finally {
            Cache::forget(self::SCANNING_PREFIX.$channel);
            $lock->release();
        }
    }
}
