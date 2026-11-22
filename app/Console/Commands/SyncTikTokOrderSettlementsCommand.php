<?php

namespace App\Console\Commands;

use App\Models\TiktokOrderSettlement;
use App\Services\TikTokOrderSettlementSync;
use App\Services\Veeqo\TikTokChargebackSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncTikTokOrderSettlementsCommand extends Command
{
    public const LOCK = 'tiktok_order_settlements_sync_run';

    protected $signature = 'tiktok:sync-settlements
        {--channel=tiktok : tiktok, tiktok2 or all}
        {--days= : Days of TikTok statements to read (default 30, or 90 when the channel has no rows yet)}
        {--order= : Read-only check: print the Finance API transactions of one order id and exit}';

    protected $description = 'Store the amount TikTok actually settles to us per order (Finance API settlement_amount) for the Final Settlement column';

    public function handle(TikTokOrderSettlementSync $sync): int
    {
        if (! TiktokOrderSettlement::tableReady()) {
            $this->error('tiktok_order_settlements table missing. Run php artisan migrate.');

            return self::FAILURE;
        }

        $channel = (string) $this->option('channel');
        if ($this->option('order')) {
            return $this->inspectOrder($sync, $channel === 'all' ? 'tiktok' : $channel, trim((string) $this->option('order')));
        }

        $channels = $channel === 'all' ? array_keys(TikTokChargebackSync::CHANNELS) : [$channel];
        $lock = Cache::lock(self::LOCK, 4 * 3600);
        if (! $lock->get()) {
            $this->error('A TikTok settlement sync is already running.');

            return self::FAILURE;
        }

        try {
            $failed = false;
            foreach ($channels as $ch) {
                if (! isset(TikTokChargebackSync::CHANNELS[$ch])) {
                    $this->error('Unknown channel '.$ch.' (use tiktok, tiktok2 or all).');

                    return self::FAILURE;
                }
                $days = $this->option('days') !== null
                    ? max(1, (int) $this->option('days'))
                    : (TikTokOrderSettlementSync::hasRows($ch) ? 30 : TikTokOrderSettlementSync::BACKFILL_DAYS);
                $this->line(TikTokChargebackSync::CHANNELS[$ch]['name'].': reading the last '.$days.' days.');
                $r = $sync->sync($ch, $days, fn (string $l) => $this->line($l));
                if ($r['types']) {
                    $this->line('Transaction types seen: '.json_encode($r['types']));
                }
                $r['done'] ? $this->info($r['message']) : $this->error($r['message']);
                $failed = $failed || ! $r['done'];
            }

            return $failed ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function inspectOrder(TikTokOrderSettlementSync $sync, string $channel, string $orderId): int
    {
        if (! isset(TikTokChargebackSync::CHANNELS[$channel])) {
            $this->error('Unknown channel '.$channel.'.');

            return self::FAILURE;
        }

        $res = $sync->fetchForOrder($channel, $orderId);
        if (! $res['ok']) {
            $this->error('Finance API failed: '.$res['error']);

            return self::FAILURE;
        }

        $this->info('Finance API '.$res['version'].' returned '.count($res['transactions']).' transaction(s) for order '.$orderId);
        foreach ($res['transactions'] as $tx) {
            $this->line(json_encode($tx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        $net = 0.0;
        foreach ($res['transactions'] as $tx) {
            if (in_array(strtoupper((string) ($tx['type'] ?? '')), TikTokOrderSettlementSync::ORDER_TYPES, true) && is_numeric($tx['settlement_amount'] ?? null)) {
                $net += (float) $tx['settlement_amount'];
            }
        }
        $this->info('Final Settlement (sum of settlement_amount, '.implode('+', TikTokOrderSettlementSync::ORDER_TYPES).'): '.number_format($net, 2));

        return self::SUCCESS;
    }
}
