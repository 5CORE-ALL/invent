<?php

namespace App\Console\Commands;

use App\Services\Support\ChannelPushSpriceDailyEnqueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class EnqueueDailyChannelPushSprice extends Command
{
    protected $signature = 'channel:push-sprice-daily {channel=all : ebay1, ebay2, ebay3, dil, or a single channel}';

    protected $description = 'Once-daily: queue S PRC → live listing price for listed SKUs (no page needed)';

    public const LOCK_CACHE_KEY = 'channel-push-sprice-daily';

    public function handle(ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        $arg = strtolower(trim((string) $this->argument('channel'))) ?: 'all';
        $lockKey = $arg === 'dil' ? self::LOCK_CACHE_KEY.'-dil' : self::LOCK_CACHE_KEY;
        $lock = Cache::lock($lockKey, 7200);
        if (! $lock->get()) {
            $this->warn('Already running — skip');

            return self::SUCCESS;
        }

        try {
            return $this->runEnqueue($enqueue);
        } finally {
            $lock->release();
        }
    }

    private function runEnqueue(ChannelPushSpriceDailyEnqueue $enqueue): int
    {
        $arg = strtolower(trim((string) $this->argument('channel'))) ?: 'all';
        $channels = ChannelPushSpriceDailyEnqueue::channelsFromArg($arg);
        if ($channels === []) {
            $this->error('Unsupported channel. Use ebay1, ebay2, ebay3, dil, or a Dil push channel.');

            return self::FAILURE;
        }

        $this->info('Daily S PRC enqueue starting ('.implode(', ', $channels).')…');

        $any = false;
        foreach ($channels as $channel) {
            $res = $enqueue->enqueueChannel($channel);
            $line = strtoupper($channel).': '.$res['message']
                .($res['spawned'] ? ' — worker started' : '');
            if (($res['queued'] ?? 0) > 0) {
                $this->info($line);
                $any = true;
            } else {
                $this->line($line);
            }
        }

        $this->info($any
            ? 'Done. Workers keep running after this command exits.'
            : 'Done. Nothing to push.');

        return self::SUCCESS;
    }
}
