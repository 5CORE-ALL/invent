<?php

namespace Tests\Unit;

use App\Services\TemuApiService;
use PHPUnit\Framework\TestCase;

class TemuModifyAdRoasBudgetTest extends TestCase
{
    public function test_daily_budget_minimum_reads_the_dollar_floor(): void
    {
        $service = new TemuApiService();

        $this->assertSame(11, $service->dailyBudgetMinimumDollars(
            'The value entered for the daily budget must be between 11 and 999,999'
        ));
        $this->assertSame(30, $service->dailyBudgetMinimumDollars(
            'The value entered for the daily budget must be between 30 and 999,999'
        ));
        $this->assertNull($service->dailyBudgetMinimumDollars('Temu did not update ROAS'));
    }
}
