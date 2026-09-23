<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Date window for the Shopping SBID and SBGT pushes.
 * Matches /google/shopping/google-shopping, which ends on the latest stored campaign date.
 */
final class GoogleShoppingPushWindow
{
    /**
     * @return array{end: string, L1: array{start: string, end: string}, L7: array{start: string, end: string}, L30: array{start: string, end: string}}
     */
    public static function ranges(?string $latestCampaignDate = null, mixed $now = null): array
    {
        $end = self::endDate($latestCampaignDate, $now);
        $endDay = Carbon::parse($end)->startOfDay();

        return [
            'end' => $endDay->toDateString(),
            'L1' => [
                'start' => $endDay->toDateString(),
                'end' => $endDay->toDateString(),
            ],
            'L7' => [
                'start' => $endDay->copy()->subDays(6)->toDateString(),
                'end' => $endDay->toDateString(),
            ],
            'L30' => [
                'start' => $endDay->copy()->subDays(29)->toDateString(),
                'end' => $endDay->toDateString(),
            ],
        ];
    }

    public static function fromCampaignTable(mixed $now = null): array
    {
        $latest = DB::table('google_ads_campaigns')->whereNotNull('date')->max('date');

        return self::ranges(is_string($latest) || $latest === null ? $latest : (string) $latest, $now);
    }

    public static function endDate(?string $latestCampaignDate, mixed $now = null): string
    {
        $latest = trim((string) $latestCampaignDate);
        if ($latest !== '') {
            return Carbon::parse($latest)->toDateString();
        }

        $today = $now instanceof Carbon ? $now->copy() : ($now === null ? now() : Carbon::parse($now));

        return $today->subDay()->toDateString();
    }
}
