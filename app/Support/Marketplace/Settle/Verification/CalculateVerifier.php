<?php

namespace App\Support\Marketplace\Settle\Verification;

use App\Models\ChannelMaster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only verification of `channel:calculate-data`, which deletes and rebuilds
 * channel_master_calculated_data in one transaction with no minimum-row guard.
 *  - calculated_rows : the table must hold (about) one row per active channel.
 *  - calculated_stale: its newest row must be from around the ledger "done" time.
 *  - duplicates      : one row per channel.
 */
final class CalculateVerifier implements Verifier
{
    public function verify(string $date, VerificationContext $context): VerificationResult
    {
        try {
            if (! Schema::hasTable('channel_master_calculated_data')) {
                return VerificationResult::error('table_missing', 'Table channel_master_calculated_data does not exist.');
            }
            $rows = (int) DB::table('channel_master_calculated_data')->count();
            $active = (int) ChannelMaster::query()->whereRaw('LOWER(TRIM(status)) = ?', ['active'])->count();
            $ratio = (float) config('settle.thresholds.calculate_min_ratio', 0.8);
            $metrics = ['rows' => $rows, 'active_channels' => $active];
            $checks = [];

            if ($rows === 0) {
                $checks[] = VerificationResult::check('calculated_rows', 'fail', 'channel_master_calculated_data is empty after the rebuild.');
            } elseif ($active > 0 && $rows < $active * $ratio) {
                $checks[] = VerificationResult::check('calculated_rows', 'fail', "Only {$rows} calculated channel rows for {$active} active channels (rebuild lost channels).", ['rows' => $rows, 'active' => $active]);
            } else {
                $checks[] = VerificationResult::check('calculated_rows', 'pass', "{$rows} calculated channel rows for {$active} active channels.");
            }

            if ($rows > 0 && Schema::hasColumn('channel_master_calculated_data', 'updated_at') && $context->doneAt) {
                $max = DB::table('channel_master_calculated_data')->max('updated_at');
                $done = Carbon::parse($context->doneAt);
                if ($max !== null && Carbon::parse((string) $max)->lt($done->copy()->subMinutes(30))) {
                    $checks[] = VerificationResult::check('calculated_stale', 'fail', 'Calculated data is older than the last settle run ('.$max.').');
                }
            }

            $dup = (int) DB::query()->fromSub(
                DB::table('channel_master_calculated_data')->select('channel')->selectRaw('COUNT(*) as c')->groupBy('channel')->havingRaw('COUNT(*) > 1'),
                'd'
            )->count();
            if ($dup > 0) {
                $checks[] = VerificationResult::check('duplicates', 'warn', "{$dup} channel(s) have more than one calculated row.");
            }

            return VerificationResult::fromChecks($checks, $metrics);
        } catch (\Throwable $e) {
            return VerificationResult::error('verifier_exception', 'Calculate verification could not run: '.mb_substr($e->getMessage(), 0, 300));
        }
    }
}
