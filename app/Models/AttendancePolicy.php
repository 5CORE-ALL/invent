<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendancePolicy extends Model
{
    protected $fillable = [
        'name',
        'timezone',
        'designation_id',
        'expected_start',
        'expected_end',
        'grace_minutes',
        'min_daily_hours',
        'max_idle_minutes_per_hour',
        'min_active_percent',
        'wfh_allowed',
        'monitoring_enabled',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'min_daily_hours' => 'decimal:2',
        'wfh_allowed' => 'boolean',
        'monitoring_enabled' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Timezone that `expected_start` is written in. Falls back to the company policy timezone
     * (India by default) so a clock-in recorded in the server's Pacific time is never compared with
     * a bare "09:30" as if it were Pacific time.
     */
    public function policyTimezone(): string
    {
        $tz = (string) ($this->timezone ?? '');

        return $tz !== '' ? $tz : (string) config('attendance.policy_timezone', 'Asia/Kolkata');
    }

    /**
     * Latest on-time clock-in (start time + grace) for the working day in which `$firstClockIn`
     * falls, evaluated in the policy timezone.
     */
    public function lateAfter(Carbon $firstClockIn): Carbon
    {
        $tz = $this->policyTimezone();
        $localDate = $firstClockIn->copy()->timezone($tz)->toDateString();

        return Carbon::parse($localDate.' '.Carbon::parse($this->expected_start)->format('H:i:s'), $tz)
            ->addMinutes((int) $this->grace_minutes);
    }

    public static function resolveForUser(User $user): ?self
    {
        if ($user->designation) {
            $designation = Designation::query()->where('name', $user->designation)->first();
            if ($designation) {
                $policy = self::query()
                    ->where('designation_id', $designation->id)
                    ->where('is_active', true)
                    ->first();
                if ($policy) {
                    return $policy;
                }
            }
        }

        $default = self::query()->where('is_default', true)->where('is_active', true)->first();
        if ($default) {
            return $default;
        }

        return self::query()->firstOrCreate(
            ['is_default' => true],
            [
                'name' => 'Default WFH Policy',
                'expected_start' => '09:30:00',
                'expected_end' => '18:30:00',
                'grace_minutes' => 15,
                'min_daily_hours' => 8.0,
                'max_idle_minutes_per_hour' => 15,
                'min_active_percent' => 60,
                'wfh_allowed' => true,
                'monitoring_enabled' => true,
                'is_active' => true,
            ]
        );
    }
}
