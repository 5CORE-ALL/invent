<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class DepartmentFeedback extends Model
{
    /**
     * One department per weekday. The popup for that department opens once on its day.
     *
     * @var array<string, array{label: string, weekday: int, icon: string, accent: string, question: string}>
     */
    public const DEPARTMENTS = [
        'software' => [
            'label' => 'Software',
            'weekday' => 1,
            'icon' => 'ri-code-s-slash-line',
            'accent' => '#4338ca',
            'question' => 'How is the software team supporting your work this week?',
        ],
        'hr' => [
            'label' => 'HR',
            'weekday' => 2,
            'icon' => 'ri-team-line',
            'accent' => '#0f766e',
            'question' => 'How is HR supporting you this week?',
        ],
        'management' => [
            'label' => 'Management',
            'weekday' => 3,
            'icon' => 'ri-briefcase-4-line',
            'accent' => '#1d4ed8',
            'question' => 'How is management supporting the team this week?',
        ],
        'sales' => [
            'label' => 'Sales',
            'weekday' => 4,
            'icon' => 'ri-line-chart-line',
            'accent' => '#b45309',
            'question' => 'How is the sales team doing this week?',
        ],
        'advertisement' => [
            'label' => 'Advertisement',
            'weekday' => 5,
            'icon' => 'ri-megaphone-line',
            'accent' => '#be123c',
            'question' => 'How are advertisements working for you this week?',
        ],
        'social_media' => [
            'label' => 'Social Media',
            'weekday' => 6,
            'icon' => 'ri-instagram-line',
            'accent' => '#6d28d9',
            'question' => 'How is social media supporting the brand this week?',
        ],
        'purchase' => [
            'label' => 'Purchase',
            'weekday' => 7,
            'icon' => 'ri-shopping-bag-3-line',
            'accent' => '#047857',
            'question' => 'How is the purchase team supporting your work this week?',
        ],
    ];

    public const WEEKDAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    protected $fillable = [
        'user_id',
        'department',
        'rating',
        'comment',
        'week_start',
        'skipped',
    ];

    protected $casts = [
        'rating' => 'integer',
        'week_start' => 'date',
        'skipped' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function weekStart(): Carbon
    {
        return now()->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    public static function todayKey(): string
    {
        $iso = now()->dayOfWeekIso;

        foreach (self::DEPARTMENTS as $key => $meta) {
            if ($meta['weekday'] === $iso) {
                return $key;
            }
        }

        return 'software';
    }

    public static function isKnown(string $department): bool
    {
        return isset(self::DEPARTMENTS[$department]);
    }

    /**
     * Today's department, when this user has not answered or skipped it this week.
     *
     * @return array<string, mixed>|null
     */
    public static function promptFor(int $userId): ?array
    {
        if ($userId < 1 || ! Schema::hasTable('department_feedbacks')) {
            return null;
        }

        $key = self::todayKey();
        $meta = self::DEPARTMENTS[$key];
        $weekStart = self::weekStart();

        $already = self::query()
            ->where('user_id', $userId)
            ->where('department', $key)
            ->where('week_start', $weekStart->toDateString())
            ->exists();

        if ($already) {
            return null;
        }

        $options = [];
        foreach (self::DEPARTMENTS as $optionKey => $option) {
            $options[] = [
                'key' => $optionKey,
                'label' => $option['label'],
                'day' => self::WEEKDAYS[$option['weekday']],
                'current' => $optionKey === $key,
            ];
        }

        return [
            'key' => $key,
            'label' => $meta['label'],
            'icon' => $meta['icon'],
            'accent' => $meta['accent'],
            'question' => $meta['question'],
            'day' => self::WEEKDAYS[$meta['weekday']],
            'week_start' => $weekStart->toDateString(),
            'date' => now()->toDateString(),
            'options' => $options,
        ];
    }
}
