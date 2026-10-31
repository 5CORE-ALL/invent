<?php

namespace App\Console\Commands;

use App\Support\IncentiveChatNotices;
use Illuminate\Console\Command;

class ChatIncentiveNotices extends Command
{
    protected $signature = 'chat:incentive-notices';

    protected $description = 'DM users via 5 Core Bot when an incentive cutoff has passed (closed) or an incentive is achieved (claim it)';

    public function handle(): int
    {
        if (! IncentiveChatNotices::ready()) {
            $this->warn('Chat or incentive tables/columns are not ready (run migrations).');

            return self::SUCCESS;
        }

        $sent = IncentiveChatNotices::sweep();
        $this->info("Closed notices: {$sent['closed']}. Achieved notices: {$sent['achieved']}.");

        return self::SUCCESS;
    }
}
