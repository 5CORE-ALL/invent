<?php

namespace App\Services;

use App\Models\AttendanceDailySummary;
use App\Models\AttendanceSession;
use App\Models\PayrollArrear;
use App\Models\PayrollEmployeeSalary;
use App\Models\PayrollMonth;
use App\Models\PayrollPaymentDeduction;
use App\Models\PayrollPayslip;
use App\Models\PayrollSalaryComponent;
use App\Models\TeamLoggerHours;
use App\Models\User;
use App\Models\UserSalary;
use App\Services\TeamLoggerService;
use Carbon\Carbon;

class PayrollService
{
    /** First N calendar days of the month come from TeamLogger; the rest from New Logger. */
    public const LOGGER_TEAM_DAYS = 18;

    public function __construct(
        protected TeamSalaryCalculator $teamSalary
    ) {}

    public function teamLoggerDataForMonth(string $monthLabel, bool $useCache = true, bool $preferApi = false): array
    {
        return $this->teamSalary->teamLoggerDataForMonth($monthLabel, $useCache, $preferApi);
    }

    public function resolveTeamLoggerEmail(string $userEmail): string
    {
        return $this->teamSalary->teamLoggerEmail($userEmail);
    }

    public function roundNet(float $amount): float
    {
        return $this->teamSalary->roundAmountP($amount);
    }

    public function defaultMonthLabel(): string
    {
        return $this->teamSalary->defaultMonthLabel();
    }

    public function periodDatesFromLabel(string $label): array
    {
        try {
            $start = Carbon::parse('first day of '.$label);
            $end = $start->copy()->endOfMonth();

            return [$start->toDateString(), $end->toDateString()];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    public function monthIncrementLabel(string $monthLabel): string
    {
        try {
            return Carbon::parse('first day of '.$monthLabel)->format('F').' Month Increment';
        } catch (\Throwable) {
            $parts = explode(' ', trim($monthLabel));

            return ($parts[0] ?? 'Month').' Month Increment';
        }
    }

    /** Team tab Amt LM */
    public function amountLm(float $hours, float $salaryLm): float
    {
        $divisor = $this->teamSalary->hoursDivisor();

        return ($hours * $salaryLm) / $divisor;
    }

    /** Team tab Amt P (before round) */
    public function amountP(float $hours, float $salaryLm, float $other, float $advIncOther): float
    {
        return $this->amountLm($hours, $salaryLm) + $other - $advIncOther;
    }

    /**
     * Base query for employees eligible to be *added* to a payroll month: users
     * marked show_in_salary who had joined on or before the month ends. Includes
     * inactive users — callers still require recorded working hours before
     * creating a row, so leavers with login hours that month still appear.
     */
    protected function eligibleUsersQuery(?PayrollMonth $month = null)
    {
        $query = User::query()
            ->where('show_in_salary', true)
            ->with('userSalary');

        if ($month && $month->period_end) {
            $joinedBy = $month->period_end->copy()->endOfDay();

            $query->where(function ($q) use ($joinedBy) {
                $q->whereNull('date_of_joining')
                    ->orWhere('date_of_joining', '<=', $joinedBy);
            });
        }

        return $query;
    }

    /**
     * Users who may receive a row when back-filling a month. Includes inactive
     * staff (so leavers who still logged hours that month can appear). Rows are
     * only created when recorded hours for the month are greater than zero.
     */
    protected function sheetPopulationQuery(PayrollMonth $month)
    {
        $query = User::query()
            ->where('show_in_salary', true)
            ->with('userSalary');

        if ($month->period_end) {
            $joinedBy = $month->period_end->copy()->endOfDay();

            $query->where(function ($q) use ($joinedBy) {
                $q->whereNull('date_of_joining')
                    ->orWhere('date_of_joining', '<=', $joinedBy);
            });
        }

        return $query;
    }

    /**
     * First 18 calendar days → TeamLogger; remaining days → built-in logger.
     * Display only — does not change Hours LM or salary amounts.
     *
     * @param  iterable<int, User|object>  $users
     * @return array<int, array{hours: float, days: int, team_hours: float, final_hours: float, team_from: string, team_to: string, new_from: ?string, new_to: string}>
     */
    public function loggerSplitHoursByUser(PayrollMonth $month, iterable $users): array
    {
        $users = collect($users)->filter(fn ($u) => $u && isset($u->id));
        if ($users->isEmpty()) {
            return [];
        }

        $split = $this->loggerSplitDates($month);
        if (! $split) {
            return [];
        }

        $userIds = $users->pluck('id')->map(fn ($id) => (int) $id)->all();
        $newLogger = $split['new_start']
            ? $this->builtLoggerHoursForRange($userIds, $split['new_start'], $split['new_end'])
            : [];

        $teamLoggerService = new TeamLoggerService();
        $teamFirst = $teamLoggerService->fetchByDateRange($split['team_start'], $split['team_end'], true);
        $teamSecond = $split['new_start']
            ? $teamLoggerService->fetchByDateRange($split['new_start'], $split['new_end'], true)
            : [];

        $hours = [];
        foreach ($users as $user) {
            $userId = (int) $user->id;
            $email = $this->resolveTeamLoggerEmail((string) ($user->email ?? ''));
            $teamHours = $this->productiveHoursFromTeamLoggerEntry($teamFirst[$email] ?? null);
            $built = $newLogger[$userId] ?? ['hours' => 0.0, 'days' => 0];
            $secondHours = $built['hours'] > 0
                ? $built['hours']
                : $this->productiveHoursFromTeamLoggerEntry($teamSecond[$email] ?? null);

            $hours[$userId] = [
                'hours' => $built['hours'],
                'days' => $built['days'],
                'team_hours' => $teamHours,
                'second_hours' => $secondHours,
                'final_hours' => $teamHours + $secondHours,
                'team_from' => $split['team_start'],
                'team_to' => $split['team_end'],
                'new_from' => $split['new_start'],
                'new_to' => $split['new_end'],
            ];
        }

        return $hours;
    }

    /**
     * Copy Final Hour (18d TeamLogger + remaining New Logger) into Hours LM
     * and recalculate salary. Marks hours as overridden so TeamLogger refresh
     * does not overwrite the split total.
     *
     * @return array{updated:int, unchanged:int, skipped_no_data:int, locked:bool}
     */
    public function syncFinalHoursToHoursLm(PayrollMonth $month, ?string $editedBy = 'Final Hour sync'): array
    {
        $stats = [
            'updated' => 0,
            'unchanged' => 0,
            'skipped_no_data' => 0,
            'locked' => (bool) $month->is_locked,
        ];

        if ($month->is_locked) {
            return $stats;
        }

        $rows = PayrollEmployeeSalary::with('user')->where('payroll_month_id', $month->id)->get();
        $split = $this->loggerSplitHoursByUser($month, $rows->pluck('user')->filter());
        $changed = false;

        foreach ($rows as $row) {
            if (! $row->user || ! array_key_exists($row->user_id, $split)) {
                $stats['skipped_no_data']++;
                continue;
            }

            $finalHours = (float) ($split[$row->user_id]['final_hours'] ?? 0);
            if ((float) $row->hours_worked === $finalHours && $row->hours_overridden) {
                $stats['unchanged']++;
                continue;
            }

            $row->update([
                'hours_worked' => $finalHours,
                'hours_overridden' => true,
                'edited_by' => $editedBy,
                'edited_at' => now(),
            ]);
            $stats['updated']++;
            $changed = true;
        }

        if ($changed) {
            $this->recalculateMonth($month);
        }

        return $stats;
    }

    /**
     * Built-in attendance-logger hours for the payroll month (display only).
     *
     * @param  array<int, int|string>  $userIds
     * @return array<int, array{hours: float, days: int}>
     */
    public function builtLoggerHoursByUser(PayrollMonth $month, array $userIds, ?string $from = null, ?string $to = null): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return [];
        }

        $start = $from;
        $end = $to;
        if (! $start || ! $end) {
            $start = $month->period_start?->toDateString();
            $end = $month->period_end?->toDateString();
            if (! $start || ! $end) {
                [$start, $end] = $this->periodDatesFromLabel($month->month_label);
            }
        }
        if (! $start || ! $end) {
            return [];
        }

        return $this->builtLoggerHoursForRange($userIds, $start, $end);
    }

    /**
     * @return array{team_start: string, team_end: string, new_start: ?string, new_end: string}|null
     */
    protected function loggerSplitDates(PayrollMonth $month): ?array
    {
        $start = $month->period_start?->copy();
        $end = $month->period_end?->copy();
        if (! $start || ! $end) {
            [$startDate, $endDate] = $this->periodDatesFromLabel($month->month_label);
            if (! $startDate || ! $endDate) {
                return null;
            }
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->startOfDay();
        }

        $teamEnd = $start->copy()->addDays(self::LOGGER_TEAM_DAYS - 1);
        if ($teamEnd->gt($end)) {
            $teamEnd = $end->copy();
        }
        $newStart = $teamEnd->copy()->addDay();

        return [
            'team_start' => $start->toDateString(),
            'team_end' => $teamEnd->toDateString(),
            'new_start' => $newStart->lte($end) ? $newStart->toDateString() : null,
            'new_end' => $end->toDateString(),
        ];
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, array{hours: float, days: int}>
     */
    protected function builtLoggerHoursForRange(array $userIds, string $start, string $end): array
    {
        $startAt = Carbon::parse($start)->startOfDay();
        $endAt = Carbon::parse($end)->endOfDay();

        $fromSummaries = AttendanceDailySummary::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('work_date', [$start, $end])
            ->selectRaw('user_id, SUM(active_seconds) as active_seconds, SUM(total_work_seconds) as work_seconds, SUM(CASE WHEN active_seconds > 0 OR total_work_seconds > 0 THEN 1 ELSE 0 END) as days')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $fromSessions = AttendanceSession::query()
            ->whereIn('user_id', $userIds)
            ->where('started_at', '<=', $endAt)
            ->where(function ($q) use ($startAt) {
                $q->whereNull('ended_at')->orWhere('ended_at', '>=', $startAt);
            })
            ->selectRaw('user_id, SUM(total_active_seconds) as active_seconds, COUNT(DISTINCT DATE(started_at)) as days')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $hours = [];
        foreach ($userIds as $userId) {
            $summary = $fromSummaries->get($userId);
            $session = $fromSessions->get($userId);

            $summaryActive = (int) ($summary?->active_seconds ?? 0);
            $summaryWork = (int) ($summary?->work_seconds ?? 0);
            $sessionActive = (int) ($session?->active_seconds ?? 0);
            $summarySeconds = $summaryActive > 0 ? $summaryActive : $summaryWork;
            $seconds = max($summarySeconds, $sessionActive);

            $hours[$userId] = [
                'hours' => $seconds > 0 ? (float) (int) round($seconds / 3600) : 0.0,
                'days' => (int) max((int) ($summary?->days ?? 0), (int) ($session?->days ?? 0)),
            ];
        }

        return $hours;
    }

    /** @param  array<string, mixed>|null  $entry */
    protected function productiveHoursFromTeamLoggerEntry(?array $entry): float
    {
        if (! $entry) {
            return 0.0;
        }

        $total = (float) ($entry['total_hours'] ?? 0);
        $idle = (float) ($entry['idle_hours'] ?? 0);
        if ($total > 0) {
            return (float) (int) round(max(0, $total - max(0, $idle)));
        }

        return (float) (int) round((float) ($entry['hours'] ?? $entry['active_hours'] ?? 0));
    }

    /**
     * Productive TeamLogger hours for a user in the given month map (0 when absent).
     *
     * @param  array<string, array<string, mixed>>  $teamLogger
     */
    protected function liveHoursForUser(User $user, array $teamLogger): float
    {
        $email = $this->resolveTeamLoggerEmail($user->email);
        if (! array_key_exists($email, $teamLogger)) {
            return 0.0;
        }

        $entry = $teamLogger[$email];
        $total = (float) ($entry['total_hours'] ?? 0);
        $idle = (float) ($entry['idle_hours'] ?? 0);

        if ($total > 0) {
            return (float) (int) round(max(0, $total - max(0, $idle)));
        }

        return (float) ($entry['hours'] ?? $entry['active_hours'] ?? 0);
    }

    /**
     * Whether a user has recorded working hours for this month's TeamLogger data.
     * Active/inactive does not matter — hours alone decide visibility.
     *
     * @param  array<string, array<string, mixed>>  $teamLogger
     */
    protected function userHasRecordedHours(User $user, array $teamLogger): bool
    {
        return $this->liveHoursForUser($user, $teamLogger) > 0;
    }

    /**
     * Whether a payroll row should stay on the sheet: any positive login/working
     * hours (live TeamLogger, manual override, or already stored). Inactive users
     * with hours are kept; anyone with zero hours is dropped.
     *
     * @param  array<string, array<string, mixed>>  $teamLogger
     */
    protected function rowHasPayableHours(PayrollEmployeeSalary $row, array $teamLogger): bool
    {
        // Manually edited hours rows stay on the sheet (including 0h) so HR can
        // keep someone visible and adjust hours without them disappearing on reload.
        if ($row->hours_overridden) {
            return true;
        }

        $user = $row->user;
        if (! $user) {
            return false;
        }

        $live = $this->liveHoursForUser($user, $teamLogger);
        if ($live > 0) {
            return true;
        }

        $email = $this->resolveTeamLoggerEmail($user->email);
        if (array_key_exists($email, $teamLogger)) {
            // Live record exists and is zero — no work this month.
            return false;
        }

        // No live TeamLogger row: keep if the sheet already has stored hours
        // (inactive leavers whose hours were synced earlier still show).
        return (float) $row->hours_worked > 0;
    }

    /**
     * Existing payroll rows to drop from an unlocked month: users excluded from
     * salary, late joiners, or deleted accounts. Hours-based pruning is handled
     * separately by removeEmployeesWithoutHours().
     *
     * @return list<int>
     */
    protected function userIdsToRemoveFromMonth(PayrollMonth $month): array
    {
        $existingUserIds = PayrollEmployeeSalary::where('payroll_month_id', $month->id)
            ->pluck('user_id')
            ->all();

        if ($existingUserIds === []) {
            return [];
        }

        $keepQuery = User::withTrashed()
            ->whereIn('id', $existingUserIds)
            ->where('show_in_salary', true);

        if ($month->period_end) {
            $joinedBy = $month->period_end->copy()->endOfDay();

            $keepQuery->where(function ($q) use ($joinedBy) {
                $q->whereNull('date_of_joining')
                    ->orWhere('date_of_joining', '<=', $joinedBy);
            });
        }

        $keepIds = $keepQuery->pluck('id')->all();

        return array_values(array_diff($existingUserIds, $keepIds));
    }

    /**
     * Drop rows on an unlocked month's sheet for users who should never appear on
     * that month (removed from salary, joined after the period, etc.).
     */
    public function removeIneligibleEmployees(PayrollMonth $month): int
    {
        if ($month->is_locked) {
            return 0;
        }

        $removeIds = $this->userIdsToRemoveFromMonth($month);

        if ($removeIds === []) {
            return 0;
        }

        return PayrollEmployeeSalary::where('payroll_month_id', $month->id)
            ->whereIn('user_id', $removeIds)
            ->delete();
    }

    /**
     * Drop sheet rows with no working hours for the month. Runs on locked months
     * too — lock freezes salary edits, not ghost rows. Inactive users are kept
     * only when they still have login hours; zero-hour rows are always removed.
     *
     * @return int Number of rows removed
     */
    public function removeEmployeesWithoutHours(PayrollMonth $month): int
    {
        $teamLogger = $this->teamLoggerDataForMonth($month->month_label);
        $removeIds = [];

        foreach (PayrollEmployeeSalary::with('user')->where('payroll_month_id', $month->id)->get() as $row) {
            // China / USA candidates stay on the sheet even without TeamLogger hours.
            if ($row->user && $row->user->isOverseasSalaryRegion()) {
                continue;
            }

            if (! $this->rowHasPayableHours($row, $teamLogger)) {
                $removeIds[] = $row->id;
            }
        }

        if ($removeIds === []) {
            return 0;
        }

        return PayrollEmployeeSalary::whereIn('id', $removeIds)->delete();
    }

    /**
     * The payroll month immediately before the given one (by period, falling back
     * to id when periods are missing). Used to carry a salary forward month over
     * month.
     */
    protected function previousMonth(PayrollMonth $month): ?PayrollMonth
    {
        $query = PayrollMonth::where('id', '!=', $month->id);

        if ($month->period_start) {
            return $query->where('period_start', '<', $month->period_start)
                ->orderByDesc('period_start')
                ->first();
        }

        return $query->where('id', '<', $month->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Carry-forward base for a user's Salary PP on a given month: the previous
     * month's (Salary PP + Increment). Returns null when there is no prior month
     * row for the user (e.g. the very first payroll month, or a brand-new hire),
     * so callers fall back to the user's stored salary.
     */
    protected function carryForwardSalaryPp(PayrollMonth $month, int $userId): ?float
    {
        $previous = $this->previousMonth($month);

        if (! $previous) {
            return null;
        }

        $previousRow = PayrollEmployeeSalary::where('payroll_month_id', $previous->id)
            ->where('user_id', $userId)
            ->first();

        if (! $previousRow) {
            return null;
        }

        return (float) $previousRow->salary_pp + (float) $previousRow->increment;
    }

    /**
     * Build the stored attributes for a freshly created month row. When a prior
     * month row exists, the Salary PP is carried forward as previous (PP +
     * Increment) and the new month starts with a 0 increment — i.e. last month's
     * raise is absorbed into this month's base pay. Hours / other / advance still
     * come from the live calculation.
     */
    protected function newRowAttributes(PayrollMonth $month, User $user, array $teamLogger): array
    {
        $calc = $this->teamSalary->calculateForUser($user, $teamLogger);

        $carry = $this->carryForwardSalaryPp($month, $user->id);
        if ($carry !== null) {
            $calc = $this->teamSalary->calculateFromValues(
                (float) $calc['hours_lm'],
                $carry,
                0.0,
                (float) $calc['other'],
                (float) $calc['adv_inc_other'],
            );
        }

        $salary = $user->userSalary;

        return [
            'salary_pp' => $calc['salary_pp'],
            'increment' => $calc['increment'],
            'other' => $calc['other'],
            'adv_inc_other' => $calc['adv_inc_other'],
            'hours_worked' => $calc['hours_lm'],
            'gross_amount' => $calc['amount_p'],
            'net_amount' => $calc['amount_p_rounded'],
            'bank_1' => $salary?->bank_1,
            'bank_2' => $salary?->bank_2,
            'upi_id' => $salary?->upi_id,
        ];
    }

    public function syncEmployeesFromUsers(PayrollMonth $month, array $userIds = [], bool $newHiresOnly = false): int
    {
        $teamLogger = $this->teamLoggerDataForMonth($month->month_label);

        $query = $this->eligibleUsersQuery($month);

        if ($userIds !== []) {
            $query->whereIn('id', $userIds);
        }

        $count = 0;
        foreach ($query->get() as $user) {
            if (! $this->userHasRecordedHours($user, $teamLogger)) {
                continue;
            }

            $exists = PayrollEmployeeSalary::where('payroll_month_id', $month->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($exists && $newHiresOnly) {
                continue;
            }

            // Carry the salary forward only for rows we are creating; existing
            // rows keep whatever salary is already on the sheet (manual edits or a
            // previously carried-forward value) so re-syncing never clobbers them.
            $attributes = $exists
                ? $this->liveCalcAttributes($user, $teamLogger)
                : $this->newRowAttributes($month, $user, $teamLogger);
            $attributes['is_new_hire'] = $newHiresOnly || ! $exists;

            PayrollEmployeeSalary::updateOrCreate(
                ['payroll_month_id' => $month->id, 'user_id' => $user->id],
                $attributes
            );
            $count++;
        }

        return $count;
    }

    /**
     * Attributes computed straight from the user's stored salary + live hours,
     * without carry-forward. Used when refreshing an existing row.
     */
    protected function liveCalcAttributes(User $user, array $teamLogger): array
    {
        $calc = $this->teamSalary->calculateForUser($user, $teamLogger);
        $salary = $user->userSalary;

        return [
            'salary_pp' => $calc['salary_pp'],
            'increment' => $calc['increment'],
            'other' => $calc['other'],
            'adv_inc_other' => $calc['adv_inc_other'],
            'hours_worked' => $calc['hours_lm'],
            'gross_amount' => $calc['amount_p'],
            'net_amount' => $calc['amount_p_rounded'],
            'bank_1' => $salary?->bank_1,
            'bank_2' => $salary?->bank_2,
            'upi_id' => $salary?->upi_id,
        ];
    }

    /**
     * Ensure every show_in_salary user with recorded hours for this month has a
     * sheet row, without touching rows that already exist. Users with no hours
     * (including inactive leavers) are not added — removeEmployeesWithoutHours()
     * drops zero-hour rows after hours refresh.
     */
    public function ensureSheetPopulated(PayrollMonth $month): int
    {
        if ($month->is_locked) {
            return 0;
        }

        $existingUserIds = PayrollEmployeeSalary::where('payroll_month_id', $month->id)
            ->pluck('user_id')
            ->all();

        $missing = $this->sheetPopulationQuery($month)
            ->when($existingUserIds !== [], fn ($q) => $q->whereNotIn('id', $existingUserIds))
            ->get();

        if ($missing->isEmpty()) {
            return 0;
        }

        $teamLogger = $this->teamLoggerDataForMonth($month->month_label);
        $count = 0;

        foreach ($missing as $user) {
            // China / USA candidates always get a row (they may not use TeamLogger).
            if (! $user->isOverseasSalaryRegion() && ! $this->userHasRecordedHours($user, $teamLogger)) {
                continue;
            }

            try {
                PayrollEmployeeSalary::create(array_merge(
                    $this->newRowAttributes($month, $user, $teamLogger),
                    [
                        'payroll_month_id' => $month->id,
                        'user_id' => $user->id,
                        'is_new_hire' => false,
                    ]
                ));
                $count++;
            } catch (\Throwable $e) {
                \Log::warning('Payroll sheet row create failed', [
                    'month' => $month->month_label,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Keep each row's Salary PP in sync with the previous month's (Salary PP +
     * Increment), so a month's base pay always reflects last month's total. The
     * per-month Increment is left untouched (it is that month's fresh raise).
     * Rows with no previous-month counterpart (first month / new hires) keep their
     * own salary. Runs only on unlocked months and recalculates when anything
     * changed.
     */
    public function syncCarryForwardSalaries(PayrollMonth $month): bool
    {
        if ($month->is_locked) {
            return false;
        }

        $previous = $this->previousMonth($month);
        if (! $previous) {
            return false;
        }

        $previousByUser = PayrollEmployeeSalary::where('payroll_month_id', $previous->id)
            ->get()
            ->keyBy('user_id');

        $changed = false;
        foreach (PayrollEmployeeSalary::where('payroll_month_id', $month->id)->get() as $row) {
            // A manually edited Salary PP wins — never overwrite it with the
            // carried-forward value.
            if ($row->salary_pp_overridden) {
                continue;
            }

            $prev = $previousByUser->get($row->user_id);
            if (! $prev) {
                continue;
            }

            $carry = (float) $prev->salary_pp + (float) $prev->increment;
            if ((float) $row->salary_pp !== $carry) {
                $row->update(['salary_pp' => $carry]);
                $changed = true;
            }
        }

        if ($changed) {
            $this->recalculateMonth($month);
        }

        return $changed;
    }

    /**
     * Refresh the bank payout details (B1 / B2 / UPI) on every existing row of an
     * unlocked month from the user's current saved bank details. Rows snapshot
     * bank details when first created, so any bank info added/updated on
     * /users/add afterwards never reached the payroll sheet — leaving some users
     * blank on the payout export. This re-pulls the latest details so the export
     * is always complete. Locked months keep their historical snapshot untouched.
     */
    public function syncBankDetails(PayrollMonth $month): bool
    {
        if ($month->is_locked) {
            return false;
        }

        $salariesByUser = UserSalary::query()
            ->whereIn('user_id', PayrollEmployeeSalary::where('payroll_month_id', $month->id)->pluck('user_id'))
            ->get()
            ->keyBy('user_id');

        $changed = false;
        foreach (PayrollEmployeeSalary::where('payroll_month_id', $month->id)->get() as $row) {
            $salary = $salariesByUser->get($row->user_id);
            if (! $salary) {
                continue;
            }

            $bank1 = $salary->bank_1;
            $bank2 = $salary->bank_2;
            $upi = $salary->upi_id;

            if ($row->bank_1 === $bank1 && $row->bank_2 === $bank2 && $row->upi_id === $upi) {
                continue;
            }

            $row->update([
                'bank_1' => $bank1,
                'bank_2' => $bank2,
                'upi_id' => $upi,
            ]);
            $changed = true;
        }

        return $changed;
    }

    public function recalculateMonth(PayrollMonth $month): void
    {
        $rows = PayrollEmployeeSalary::where('payroll_month_id', $month->id)->get();

        foreach ($rows as $row) {
            $calc = $this->teamSalary->calculateFromValues(
                (float) $row->hours_worked,
                (float) $row->salary_pp,
                (float) $row->increment,
                (float) $row->other,
                (float) $row->adv_inc_other,
                (float) $row->incentive
            );

            $userId = $row->user_id;
            $componentsEarning = (float) PayrollSalaryComponent::where('payroll_month_id', $month->id)
                ->where('user_id', $userId)->where('type', 'earning')->sum('amount');
            $componentsDeduction = (float) PayrollSalaryComponent::where('payroll_month_id', $month->id)
                ->where('user_id', $userId)->where('type', 'deduction')->sum('amount');
            $payments = (float) PayrollPaymentDeduction::where('payroll_month_id', $month->id)
                ->where('user_id', $userId)->where('entry_type', 'payment')->sum('amount');
            $deductions = (float) PayrollPaymentDeduction::where('payroll_month_id', $month->id)
                ->where('user_id', $userId)->where('entry_type', 'deduction')->sum('amount');
            $arrears = $this->appliedArrearsTotal($month->id, $userId);

            // "Amount" = ((PP + Increment) * Hours / 200) - Advance + Other + Incentive.
            // "Payable" (net) layers the month's components, payments and arrears on top.
            $amount = $calc['amount_p'];
            $hasExtras = $componentsEarning > 0 || $componentsDeduction > 0 || $payments > 0 || $deductions > 0 || abs($arrears) > 0.001;
            $net = $hasExtras
                ? $this->roundNet($amount + $componentsEarning - $componentsDeduction + $payments - $deductions + $arrears)
                : $calc['amount_p_rounded'];

            $row->update([
                'gross_amount' => $amount,
                'lop_amount' => 0,
                'arrears_amount' => $arrears,
                'payments_total' => $payments,
                'deductions_total' => $deductions + $componentsDeduction,
                'net_amount' => $net,
            ]);
        }
    }

    /**
     * Refresh stored working hours from live TeamLogger for an unlocked month, then
     * recompute amounts. Salary inputs (PP, increment, other, adv) are kept as stored,
     * and users with no live TeamLogger entry keep their existing hours.
     *
     * @return array{updated:int, skipped_overridden:int, skipped_no_data:int, unchanged:int, teamlogger_users:int, locked:bool}
     */
    public function refreshLiveHours(PayrollMonth $month, bool $freshFromApi = false): array
    {
        $stats = [
            'updated' => 0,
            'skipped_overridden' => 0,
            'skipped_no_data' => 0,
            'unchanged' => 0,
            'teamlogger_users' => 0,
            'locked' => (bool) $month->is_locked,
        ];

        if ($month->is_locked) {
            return $stats;
        }

        if ($freshFromApi) {
            (new TeamLoggerService())->clearCacheForMonth($month->month_label);
        }

        $teamLogger = $this->teamLoggerDataForMonth($month->month_label, ! $freshFromApi, $freshFromApi);
        $stats['teamlogger_users'] = count($teamLogger);

        if ($freshFromApi && $teamLogger !== []) {
            $this->persistTeamLoggerHours($month->month_label, $teamLogger);
        }

        $changed = false;
        $respectOverrides = ! $freshFromApi;

        foreach (PayrollEmployeeSalary::with('user')->where('payroll_month_id', $month->id)->get() as $row) {
            if (! $row->user) {
                continue;
            }
            if ($respectOverrides && $row->hours_overridden) {
                $stats['skipped_overridden']++;
                continue;
            }
            $email = $this->resolveTeamLoggerEmail($row->user->email);
            if (! array_key_exists($email, $teamLogger)) {
                $stats['skipped_no_data']++;
                continue;
            }
            // Payroll Hours LM = productive only (TeamLogger total − idle). Never include idle.
            $hours = $this->liveHoursForUser($row->user, $teamLogger);
            $hoursChanged = (float) $row->hours_worked !== $hours;
            $clearOverride = $freshFromApi && $row->hours_overridden;

            if ($hoursChanged || $clearOverride) {
                $payload = ['hours_worked' => $hours];
                if ($freshFromApi) {
                    $payload['hours_overridden'] = false;
                }
                $row->update($payload);
                $stats['updated']++;
                $changed = true;
            } else {
                $stats['unchanged']++;
            }
        }

        if ($changed) {
            $this->recalculateMonth($month);
        }

        return $stats;
    }

    /** @param  array<string, array<string, mixed>>  $teamLogger */
    protected function persistTeamLoggerHours(string $monthLabel, array $teamLogger): void
    {
        [$start, $end] = $this->periodDatesFromLabel($monthLabel);
        if (! $start || ! $end) {
            return;
        }

        foreach ($teamLogger as $email => $hours) {
            $total = (float) ($hours['total_hours'] ?? 0);
            $idle = (float) ($hours['idle_hours'] ?? 0);
            $productive = $total > 0
                ? (int) round(max(0, $total - max(0, $idle)))
                : (int) round((float) ($hours['hours'] ?? $hours['active_hours'] ?? 0));

            TeamLoggerHours::updateOrCreate(
                [
                    'employee_email' => strtolower(trim((string) $email)),
                    'month' => $monthLabel,
                ],
                [
                    'start_date' => $start,
                    'end_date' => $end,
                    'productive_hours' => $productive,
                    'total_hours' => $total,
                    'idle_hours' => $idle,
                    'active_hours' => $productive,
                    'fetched_at' => now(),
                ]
            );
        }
    }

    /**
     * @return array<int, array{0: string, 1: float}>
     */
    public function buildPayslipEarnings(array $data): array
    {
        $monthLabel = (string) ($data['month'] ?? '');
        $hours = (float) ($data['hours_worked'] ?? 0);
        $salaryPp = (float) ($data['salary_pp'] ?? 0);
        $increment = (float) ($data['increment'] ?? 0);
        $divisor = $this->teamSalary->hoursDivisor();
        $skip = ['salary', 'basic', 'basic pay', 'salary lm', 'salary pp', 'incr', 'increment', 'gross', 'amt lm'];

        $lines = [];
        if ($hours > 0 && $salaryPp > 0) {
            $lines[] = [
                'Basic Pay',
                (float) round(($hours * $salaryPp) / $divisor),
            ];
        }
        if ($increment > 0) {
            $lines[] = [
                $this->monthIncrementLabel($monthLabel),
                (float) $increment,
            ];
        }
        if (($data['other'] ?? 0) > 0) {
            $lines[] = ['Other Allowance', (float) $data['other']];
        }
        if (($data['incentive'] ?? 0) > 0) {
            $lines[] = ['Incentive', (float) $data['incentive']];
        }
        foreach ($data['components'] ?? [] as $c) {
            if (($c['type'] ?? '') !== 'earning' || ($c['amount'] ?? 0) <= 0) {
                continue;
            }
            $label = strtolower(trim((string) ($c['label'] ?? '')));
            if (in_array($label, $skip, true) || str_contains($label, 'increment')) {
                continue;
            }
            $lines[] = [(string) $c['label'], (float) $c['amount']];
        }
        foreach ($data['arrear_lines'] ?? [] as $line) {
            $amt = (float) ($line['amount'] ?? 0);
            if (abs($amt) > 0.001) {
                $lines[] = [(string) ($line['label'] ?? 'Arrear'), $amt];
            }
        }

        return $lines;
    }

    public function appliedArrearsTotal(int $payrollMonthId, int $userId): float
    {
        return (float) PayrollArrear::where('payroll_month_id', $payrollMonthId)
            ->where('user_id', $userId)
            ->where('status', 'applied')
            ->get()
            ->sum(fn (PayrollArrear $a) => $a->signedAmount());
    }

    /** @return array<int, array{label: string, amount: float}> */
    public function appliedArrearLines(int $payrollMonthId, int $userId): array
    {
        return PayrollArrear::where('payroll_month_id', $payrollMonthId)
            ->where('user_id', $userId)
            ->where('status', 'applied')
            ->orderBy('id')
            ->get()
            ->map(fn (PayrollArrear $a) => [
                'label' => $a->displayLabel(),
                'amount' => $a->signedAmount(),
            ])
            ->values()
            ->all();
    }

    /** Build payslip payload from current payroll employee row (same as Employees tab). */
    public function buildPayslipData(PayrollMonth $month, PayrollEmployeeSalary $row, ?string $format = null): array
    {
        $row->loadMissing('user');
        $format ??= $month->payslip_format ?: 'standard';
        $userId = (int) $row->user_id;

        $calc = $this->teamSalary->calculateFromValues(
            (float) $row->hours_worked,
            (float) $row->salary_pp,
            (float) $row->increment,
            (float) $row->other,
            (float) $row->adv_inc_other,
            (float) $row->incentive
        );

        $components = PayrollSalaryComponent::where('payroll_month_id', $month->id)
            ->where('user_id', $userId)->get();
        $componentsEarning = (float) $components->where('type', 'earning')->sum('amount');
        $componentsDeduction = (float) $components->where('type', 'deduction')->sum('amount');
        $payments = (float) $row->payments_total;
        $deductions = (float) $row->deductions_total;
        $arrearLines = $this->appliedArrearLines($month->id, $userId);
        $arrears = array_sum(array_column($arrearLines, 'amount'));

        $data = [
            'employee' => $row->user?->name,
            'email' => $row->user?->email,
            'designation' => $row->user?->designation,
            'employee_id' => 'EMP-'.str_pad((string) $userId, 4, '0', STR_PAD_LEFT),
            'month' => $month->month_label,
            'period_start' => $month->period_start?->format('d M Y'),
            'period_end' => $month->period_end?->format('d M Y'),
            'payslip_no' => 'PS-'.$month->id.'-'.$userId,
            'format' => $format,
            'salary_pp' => $calc['salary_pp'],
            'increment' => $calc['increment'],
            'salary_lm' => $calc['salary_lm'],
            'other' => $calc['other'],
            'adv_inc_other' => $calc['adv_inc_other'],
            'incentive' => $calc['incentive'],
            'hours_worked' => $calc['hours_lm'],
            'amount_lm' => $calc['amount_lm'],
            'amount_lm_display' => $calc['amount_lm_display'],
            'amount_p' => $calc['amount_p'],
            'amount_p_rounded' => $calc['amount_p_rounded'],
            'amount_p_display' => $calc['amount_p_display'],
            'gross' => $calc['amount_lm'],
            'net' => (float) $row->net_amount,
            'arrears' => $arrears,
            'arrear_lines' => $arrearLines,
            'payments' => $payments,
            'deductions' => $deductions,
            'bank_1' => $row->bank_1,
            'bank_2' => $row->bank_2,
            'upi_id' => $row->upi_id,
            'components' => $components->map(fn ($c) => [
                'type' => $c->type,
                'label' => $c->label,
                'amount' => (float) $c->amount,
            ])->values()->all(),
            'generated_at' => now()->format('d M Y, h:i A'),
        ];

        $data['earning_lines'] = $this->buildPayslipEarnings($data);
        $data['total_earnings'] = round(array_sum(array_column($data['earning_lines'], 1)));

        return $data;
    }

    public function generatePayslips(PayrollMonth $month): int
    {
        $format = $month->payslip_format ?: 'standard';
        $rows = PayrollEmployeeSalary::with('user')->where('payroll_month_id', $month->id)->get();
        $count = 0;

        foreach ($rows as $row) {
            $data = $this->buildPayslipData($month, $row, $format);
            if ($format === 'compact') {
                $data['summary_only'] = true;
            }

            PayrollPayslip::updateOrCreate(
                ['payroll_month_id' => $month->id, 'user_id' => $row->user_id],
                ['format' => $format, 'data' => $data]
            );
            $count++;
        }

        return $count;
    }
}
