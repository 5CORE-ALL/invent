<?php

namespace App\Console\Commands;

use App\Support\ChatWorkspace;
use App\Support\InventChatBot;
use App\Support\TaskBusinessTime;
use Illuminate\Console\Command;

class ChatDailyNudges extends Command
{
    protected $signature = 'chat:daily-nudges';

    protected $description = 'Send once-a-day DAR, overdue, and yesterday-missed 5 Core Bot DMs';

    public function handle(): int
    {
        if (! ChatWorkspace::tablesReady()) {
            $this->warn('Chat tables are not ready.');

            return self::SUCCESS;
        }

        $now = TaskBusinessTime::now();
        if ($now->isWeekend()) {
            $this->info('Weekend — skipping chat nudges.');

            return self::SUCCESS;
        }

        ChatWorkspace::syncPublicMembers();

        $users = ChatWorkspace::activeUsersQuery()
            ->orderBy('id')
            ->get(['id', 'name', 'email']);

        $darSent = 0;
        $overdueSent = 0;
        $missedSent = 0;

        foreach ($users as $user) {
            $inbox = ChatWorkspace::botInboxFor($user);

            $darBody = InventChatBot::dailyDarBody($user);
            if ($darBody && ! InventChatBot::alreadyNudgedToday($inbox, 'daily_dar')) {
                InventChatBot::postBot($inbox, $darBody, 'daily_dar');
                ChatWorkspace::forgetUnreadCache((int) $user->id);
                $darSent++;
            }

            $overdueBody = InventChatBot::dailyOverdueBody($user);
            if ($overdueBody && ! InventChatBot::alreadyNudgedToday($inbox, 'daily_overdue')) {
                InventChatBot::postBot($inbox, $overdueBody, 'daily_overdue');
                ChatWorkspace::forgetUnreadCache((int) $user->id);
                $overdueSent++;
            }

            $missedBody = InventChatBot::dailyMissedYesterdayBody($user);
            if ($missedBody && ! InventChatBot::alreadyNudgedToday($inbox, 'daily_missed_yesterday')) {
                InventChatBot::postBot($inbox, $missedBody, 'daily_missed_yesterday');
                ChatWorkspace::forgetUnreadCache((int) $user->id);
                $missedSent++;
            }
        }

        $this->info("DAR nudges: {$darSent}. Overdue nudges: {$overdueSent}. Yesterday missed: {$missedSent}.");

        return self::SUCCESS;
    }
}
