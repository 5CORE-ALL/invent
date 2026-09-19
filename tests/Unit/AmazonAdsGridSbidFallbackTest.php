<?php

namespace Tests\Unit;

use App\Http\Controllers\AmazonAdsController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AmazonAdsGridSbidFallbackTest extends TestCase
{
    public function test_missing_util_with_budget_is_treated_as_zero(): void
    {
        $out = $this->sbidUtils(['U7' => null, 'U1' => null], ['campaignBudgetAmount' => 6]);

        $this->assertSame(['u2' => 0.0, 'u1' => 0.0], $out);
    }

    public function test_missing_util_without_budget_stays_blank(): void
    {
        $this->assertNull($this->sbidUtils(['U7' => null, 'U1' => null], []));
    }

    /**
     * @param  array<string, mixed>  $u
     * @param  array<string, mixed>  $row
     * @return array{u2: float, u1: float}|null
     */
    private function sbidUtils(array $u, array $row): ?array
    {
        $method = new ReflectionMethod(AmazonAdsController::class, 'sbidRuleUtilsFromUtilization');
        $method->setAccessible(true);

        return $method->invoke(null, $u, $row);
    }
}
