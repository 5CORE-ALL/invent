<?php

namespace App\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Meeting times are stored like every other column (in config('app.timezone')) but are read back
 * in the company's meeting timezone (services.meetings.timezone, default Asia/Kolkata), so every
 * ->format() in the meeting pages shows the time people actually experienced.
 * Strings without an offset are interpreted in the meeting timezone; ISO strings with an offset
 * ("...Z") are exact instants.
 *
 * Usage: MeetingDateTime::class  or  MeetingDateTime::class.':ms'  (millisecond precision columns)
 */
class MeetingDateTime implements CastsAttributes
{
    public function __construct(private string $precision = '')
    {
    }

    public static function timezone(): string
    {
        return (string) config('services.meetings.timezone', 'Asia/Kolkata');
    }

    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value, config('app.timezone'))->setTimezone(self::timezone());
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $carbon = $value instanceof \DateTimeInterface
            ? Carbon::instance($value)
            : Carbon::parse((string) $value, self::timezone());

        return $carbon->setTimezone(config('app.timezone'))
            ->format($this->precision === 'ms' ? 'Y-m-d H:i:s.v' : 'Y-m-d H:i:s');
    }
}
