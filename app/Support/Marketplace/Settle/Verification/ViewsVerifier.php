<?php

namespace App\Support\Marketplace\Settle\Verification;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only verification of the dated one-day views stored in channel_yesterday_views.
 *
 * A stored 0 means "no traffic data pulled" (the views commands exit 0 and may write 0), so it is
 * not accepted as verified when the channel normally has views.
 *
 * spec: channels (list), depends_on (task key that actually writes the dated rows), amazon (bool).
 */
final class ViewsVerifier implements Verifier
{
    private const PACIFIC = 'America/Los_Angeles';

    /** @param  array<string, mixed>  $spec */
    public function __construct(private array $spec) {}

    public function verify(string $date, VerificationContext $context): VerificationResult
    {
        try {
            $channels = $this->spec['channels'] ?? [];
            $amazon = ! empty($this->spec['amazon']);
            if ($channels === [] && ! $amazon) {
                return new VerificationResult(VerificationResult::LIMITED_BY_DESIGN, [VerificationResult::check(
                    'no_dated_source',
                    'limited_by_design',
                    'This task has no dated one-day views table to compare with; it is verified from the command output only.'
                )]);
            }
            if (! Schema::hasTable('channel_yesterday_views')) {
                return VerificationResult::error('table_missing', 'Table channel_yesterday_views does not exist, so views cannot be verified.');
            }

            $dep = $this->spec['depends_on'] ?? null;
            if ($dep !== null && ! $context->otherTaskDone($dep)) {
                return new VerificationResult(VerificationResult::PENDING, [VerificationResult::check(
                    'waiting_for_task',
                    'pending',
                    "Waiting for task {$dep}, which stores the dated views this task is checked against."
                )]);
            }

            $minDays = (int) config('settle.thresholds.baseline_min_days', 3);
            $checks = [];
            $metrics = [];

            foreach ($channels as $ch) {
                $views = $this->viewsOn($ch, $date);
                $history = $this->daysWithViews($ch, Carbon::parse($date, self::PACIFIC)->subDays(7)->toDateString(), Carbon::parse($date, self::PACIFIC)->subDay()->toDateString());
                $metrics[$ch] = ['views' => $views, 'history_days' => $history];
                if ($views !== null && $views > 0) {
                    $checks[] = VerificationResult::check('views_'.$ch, 'pass', "{$ch}: {$views} views stored for the date.");
                } elseif ($history >= $minDays) {
                    $checks[] = VerificationResult::check('views_missing', 'fail', "{$ch}: no views stored for {$date} (".($views === null ? 'no row' : 'stored 0').") while it had views on {$history} of the last 7 days.", ['channel' => $ch]);
                } else {
                    $checks[] = VerificationResult::check('views_no_history', 'limited', "{$ch}: no views for {$date} but not enough history ({$history} days) to call it a failure.", ['channel' => $ch]);
                }
            }

            if ($amazon) {
                $checks[] = $this->amazonCheck($date, $metrics);
            }

            return VerificationResult::fromChecks($checks, $metrics);
        } catch (\Throwable $e) {
            return VerificationResult::error('verifier_exception', 'Views verification could not run: '.mb_substr($e->getMessage(), 0, 300));
        }
    }

    /**
     * Amazon traffic settles ~3 days after a day ends, so the settled days D-5..D-2 are checked.
     *
     * @param  array<string, mixed>  $metrics
     */
    private function amazonCheck(string $date, array &$metrics): array
    {
        $d = Carbon::parse($date, self::PACIFIC);
        $recent = DB::table('channel_yesterday_views')->where('channel', 'amazon')
            ->whereDate('snapshot_date', '>=', $d->copy()->subDays(5)->toDateString())
            ->whereDate('snapshot_date', '<=', $d->copy()->subDays(2)->toDateString())
            ->where('views', '>', 0)->count();
        $older = DB::table('channel_yesterday_views')->where('channel', 'amazon')
            ->whereDate('snapshot_date', '>=', $d->copy()->subDays(20)->toDateString())
            ->whereDate('snapshot_date', '<', $d->copy()->subDays(5)->toDateString())
            ->where('views', '>', 0)->count();
        $metrics['amazon'] = ['settled_days_with_views' => $recent, 'older_days_with_views' => $older];

        if ($recent > 0) {
            return VerificationResult::check('views_amazon', 'pass', "Amazon: views stored for {$recent} settled day(s) among D-5..D-2.");
        }
        if ($older > 0) {
            return VerificationResult::check('views_missing', 'fail', 'Amazon: no settled-day views stored for D-5..D-2 although earlier days have them.', ['channel' => 'amazon']);
        }

        return VerificationResult::check('views_no_history', 'limited', 'Amazon: no settled-day views and no history to compare with.', ['channel' => 'amazon']);
    }

    private function viewsOn(string $channel, string $ymd): ?int
    {
        $r = DB::table('channel_yesterday_views')->where('channel', $channel)->whereDate('snapshot_date', $ymd)->first(['views']);

        return $r === null ? null : (int) $r->views;
    }

    private function daysWithViews(string $channel, string $from, string $to): int
    {
        return (int) DB::table('channel_yesterday_views')->where('channel', $channel)
            ->whereDate('snapshot_date', '>=', $from)->whereDate('snapshot_date', '<=', $to)
            ->where('views', '>', 0)->count();
    }
}
