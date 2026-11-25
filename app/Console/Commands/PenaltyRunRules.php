<?php

namespace App\Console\Commands;

use App\Models\PenaltyRule;
use App\Support\ChatRules;
use App\Support\Penalty;
use App\Support\TaskBusinessTime;
use Illuminate\Console\Command;

class PenaltyRunRules extends Command
{
    protected $signature = 'penalty:run-rules {--force : Ignore the run time and the once-a-day check} {--rule= : Only this rule id}';

    protected $description = 'Add penalty points for the active auto rules set by president@5core.com (Penalty icon)';

    /** A rule is picked up at its run time and for this long after it (minutes). */
    private const WINDOW_MINUTES = 180;

    public function handle(): int
    {
        if (! Penalty::tablesReady()) {
            $this->warn('Penalty tables are not ready.');

            return self::SUCCESS;
        }

        $now = TaskBusinessTime::now();
        $force = (bool) $this->option('force');

        $query = PenaltyRule::query()->where('is_active', true);
        if ($this->option('rule')) {
            $query->whereKey((int) $this->option('rule'));
        }

        $due = [];
        foreach ($query->get() as $rule) {
            if (! $force) {
                if ($rule->weekdays_only && $now->isWeekend()) {
                    continue;
                }
                [$h, $m] = array_map('intval', explode(':', (string) $rule->run_time) + [0, 0]);
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
            $this->info('No penalty rules due.');

            return self::SUCCESS;
        }

        $rows = ChatRules::memberRows();
        foreach ($due as $rule) {
            $r = Penalty::runRule($rule, $rows);
            $this->info("Rule #{$rule->id} {$rule->name}: matched {$r['matched']}, added {$r['added']}, already had it {$r['already']}.");
        }

        return self::SUCCESS;
    }
}
