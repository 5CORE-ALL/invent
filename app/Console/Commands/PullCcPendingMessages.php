<?php

namespace App\Console\Commands;

use App\Models\ChannelMaster;
use App\Services\CustomerCare\MarketplacePendingMessagesService;
use Illuminate\Console\Command;

class PullCcPendingMessages extends Command
{
    protected $signature = 'cc:pull-pending-messages';

    protected $description = 'Pull pending seller-message counts from each marketplace API for Customer Care.';

    public function handle(MarketplacePendingMessagesService $service): int
    {
        $channels = ChannelMaster::query()
            ->where('status', 'Active')
            ->orderBy('channel')
            ->get(['id', 'channel']);

        $ok = 0;
        $unsupported = 0;
        $error = 0;

        foreach ($channels as $channel) {
            if ($service->driverFor($channel) === null) {
                $service->fetchAndStore($channel);
                $unsupported++;
                $this->line('— '.$channel->channel.' (no API)');
                continue;
            }

            $row = $service->fetchAndStore($channel);
            $status = $row['fetch_status'] ?? 'error';
            if ($status === MarketplacePendingMessagesService::STATUS_OK) {
                $ok++;
                $this->info($channel->channel.': '.$row['pending_count']);
            } elseif ($status === MarketplacePendingMessagesService::STATUS_UNSUPPORTED) {
                $unsupported++;
                $this->line('— '.$channel->channel.' (no inbox API)');
            } else {
                $error++;
                $this->warn($channel->channel.': '.($row['fetch_note'] ?? 'error'));
            }
        }

        $this->info("Done. ok={$ok} unsupported={$unsupported} error={$error}");

        return self::SUCCESS;
    }
}
