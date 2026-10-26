<?php

namespace App\Console\Commands;

use App\Services\Veeqo\ChargebackOsizeSync;
use App\Services\Veeqo\TikTokChargebackSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncTikTokChargebacksCommand extends Command
{
    public const LOCK = 'tiktok_chargebacks_sync_run';

    protected $signature = 'tiktok:sync-chargebacks
        {--channel=all : tiktok, tiktok2 or all}
        {--days= : How many days of TikTok statements to read (default 30, or 365 when the channel has no rows yet)}';

    protected $description = 'Copy TikTok Shop shipping label charges and shipping fee adjustments (Finance API) into veeqo_chargebacks';

    public function handle(TikTokChargebackSync $sync): int
    {
        $channel = (string) $this->option('channel');
        $channels = $channel === 'all' ? array_keys(TikTokChargebackSync::CHANNELS) : [$channel];
        $lock = Cache::lock(self::LOCK, 4 * 3600);
        if (! $lock->get()) {
            $this->error('A TikTok chargeback sync is already running.');

            return self::FAILURE;
        }
        try {
            return $this->syncChannels($sync, $channels);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<string>  $channels
     */
    private function syncChannels(TikTokChargebackSync $sync, array $channels): int
    {
        $failed = false;

        foreach ($channels as $ch) {
            if (! isset(TikTokChargebackSync::CHANNELS[$ch])) {
                $this->error('Unknown channel '.$ch.' (use tiktok, tiktok2 or all).');

                return self::FAILURE;
            }
            $days = $this->option('days') !== null
                ? max(1, (int) $this->option('days'))
                : (TikTokChargebackSync::hasRows($ch) ? 30 : TikTokChargebackSync::BACKFILL_DAYS);
            $this->line(TikTokChargebackSync::CHANNELS[$ch]['name'].': reading the last '.$days.' days.');
            $r = $sync->sync($ch, $days, fn (string $l) => $this->line($l));
            if ($r['types']) {
                $this->line('Transaction types seen: '.json_encode($r['types']));
            }
            $r['done'] ? $this->info($r['message']) : $this->error($r['message']);
            $failed = $failed || ! $r['done'];
        }

        // New chargebacks change the report's Avg Chargeback Total, so push it to Shipping Master O-Size now.
        if (! $failed) {
            $this->line(ChargebackOsizeSync::summary(app(ChargebackOsizeSync::class)->runAfterNewData()));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
