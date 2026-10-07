<?php

namespace Tests\Unit;

use App\Services\FaireApiService;
use PHPUnit\Framework\TestCase;

class FaireRetailRangeTest extends TestCase
{
    public function test_keeps_retail_when_it_is_already_inside_faire_window(): void
    {
        // wholesale $20.00, retail $40.00 → 2×
        $this->assertSame(4000, FaireApiService::retailMinorWithinFaireRange(2000, 4000));
    }

    public function test_raises_retail_to_at_least_1_25x_wholesale(): void
    {
        // $19.99 wholesale, $18.99 retail (equal / too close) → ceil($19.99 × 1.25) = $24.99
        $this->assertSame(2499, FaireApiService::retailMinorWithinFaireRange(1999, 1899));
        // $20.99 wholesale, $25.99 retail is 1.24× → ceil($20.99 × 1.25) = $26.24
        $this->assertSame(2624, FaireApiService::retailMinorWithinFaireRange(2099, 2599));
    }

    public function test_caps_retail_at_10x_wholesale(): void
    {
        // $22.99 wholesale, $520 retail → $229.90
        $this->assertSame(22990, FaireApiService::retailMinorWithinFaireRange(2299, 52000));
    }

    public function test_missing_retail_uses_double_wholesale(): void
    {
        $this->assertSame(4000, FaireApiService::retailMinorWithinFaireRange(2000, 0));
    }
}
