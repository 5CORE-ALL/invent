<?php

namespace App\Console\Commands;

use App\Models\ChannelMaster;
use App\Services\CustomerCare\MarketplacePendingReturnsService;
use Illuminate\Console\Command;

class PullCcPendingReturns extends Command
{
    protected $signature = 'cc:pull-pending-returns';

    protected $description = 'Pull pending-return counts from each marketplace returns API for the Customer Care report.';

    public function handle(MarketplacePendingReturnsService $service): int
    {
        $channels = ChannelMaster::query()
            ->where('status', 'Active')
            ->orderBy('channel')
            ->get(['id', 'channel']);

        $ok = 0;
        $unsupported = 0;
        $error = 0;

        foreach ($channels as $channel) {
            $row = $service->fetchAndStore($channel);
            $status = $row['fetch_status'] ?? 'error';
            if ($status === MarketplacePendingReturnsService::STATUS_OK) {
                $ok++;
                $this->info($channel->channel.': '.$row['pending_count']);
            } elseif ($status === MarketplacePendingReturnsService::STATUS_UNSUPPORTED) {
                $unsupported++;
                $this->line('— '.$channel->channel.' (no returns API)');
            } else {
                $error++;
                $this->warn($channel->channel.': '.($row['fetch_note'] ?? 'error'));
            }
        }

        $this->info("Done. ok={$ok} unsupported={$unsupported} error={$error}");

        return self::SUCCESS;
    }
}
