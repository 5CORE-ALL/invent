<?php

namespace App\Support\Badges;

use App\Contracts\PageBadgeCalculator;
use App\Http\Controllers\Channels\CustomerCareHealthController;
use App\Http\Controllers\Channels\ShippingHealthOverviewController;
use ReflectionClass;
use Throwable;

class AccountHealthBadgeCalculator implements PageBadgeCalculator
{
    public const PAGE_NAME = 'account-health';

    public static function pageName(): string
    {
        return self::PAGE_NAME;
    }

    public static function syncBeforeCalculate(): void
    {
        //
    }

    /**
     * R/Y/G tone counts from Customer Care Health + Shipping Health (page pie badges).
     *
     * @return array{
     *     cc_red: int, cc_yellow: int, cc_green: int, cc_unrated: int,
     *     ship_red: int, ship_yellow: int, ship_green: int, ship_unrated: int
     * }
     */
    public static function calculate(): array
    {
        $cc = self::toneCounts(CustomerCareHealthController::class);
        $ship = self::toneCounts(ShippingHealthOverviewController::class);

        return [
            'cc_red' => $cc['red'],
            'cc_yellow' => $cc['yellow'],
            'cc_green' => $cc['green'],
            'cc_unrated' => $cc['unrated'],
            'ship_red' => $ship['red'],
            'ship_yellow' => $ship['yellow'],
            'ship_green' => $ship['green'],
            'ship_unrated' => $ship['unrated'],
        ];
    }

    /**
     * @return array{red: int, yellow: int, green: int, unrated: int}
     */
    private static function toneCounts(string $controllerClass): array
    {
        $defaults = ['red' => 0, 'yellow' => 0, 'green' => 0, 'unrated' => 0];

        try {
            $controller = app($controllerClass);
            $ref = new ReflectionClass($controller);
            $build = $ref->getMethod('buildChannelRows');
            $build->setAccessible(true);
            $count = $ref->getMethod('countTones');
            $count->setAccessible(true);
            $tones = $count->invoke($controller, $build->invoke($controller));

            return array_merge($defaults, is_array($tones) ? $tones : []);
        } catch (Throwable $e) {
            return $defaults;
        }
    }
}
