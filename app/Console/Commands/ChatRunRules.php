<?php

namespace App\Console\Commands;

use App\Models\ChatRule;
use App\Support\ChatRules;
use App\Support\ChatWorkspace;
use App\Support\TaskBusinessTime;
use Illuminate\Console\Command;

class ChatRunRules extends Command
{
    protected $signature = 'chat:run-rules {--force : Ignore the send time and the once-a-day check} {--rule= : Only this rule id}';

    protected $description = 'Send the 5 Core Bot messages for the active Chat Rules (set on /tasks/summary)';

    /** A rule is picked up at its send time and for this long after it (minutes). */
    private const WINDOW_MINUTES = 180;

    public function handle(): int
    {
        if (! ChatRules::tablesReady()) {
            $this->warn('Chat rule tables are not ready.');

            return self::SUCCESS;
        }

        $now = TaskBusinessTime::now();
        $force = (bool) $this->option('force');

        $query = ChatRule::query()->where('is_active', true);
        if ($this->option('rule')) {
            $query->whereKey((int) $this->option('rule'));
        }

        $due = [];
        foreach ($query->get() as $rule) {
            if (! $force) {
                if ($rule->weekdays_only && $now->isWeekend()) {
                    continue;
                }
                [$h, $m] = array_map('intval', explode(':', (string) $rule->send_time) + [0, 0]);
                $start = $now->copy()->setTime($h, $m, 0);
                if ($now->lt($start) || $now->gte($start->copy()->addMinutes(self::WINDOW_MINUTES))) {
                    continue;
                }
                if ($rule->last_run_at && $rule->last_run_at->copy()->timezone($now->timezone)->isSameDay($now)) {
                    continue; // already ran today
                }
            }
            $due[] = $rule;
        }

        if ($due === []) {
            $this->info('No chat rules due.');

            return self::SUCCESS;
        }

        ChatWorkspace::syncPublicMembers();
        $rows = ChatRules::memberRows();

        foreach ($due as $rule) {
            $result = ChatRules::run($rule, false, $rows);
            $this->info("Rule #{$rule->id} {$rule->name}: matched {$result['matched']}, sent {$result['sent']}, already had it {$result['already']}.");
        }

        return self::SUCCESS;
    }
}
