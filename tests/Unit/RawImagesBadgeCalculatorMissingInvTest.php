<?php

namespace Tests\Unit;

use App\Support\Badges\RawImagesBadgeCalculator;
use ReflectionClass;
use Tests\TestCase;

class RawImagesBadgeCalculatorMissingInvTest extends TestCase
{
    public function test_calculate_source_excludes_zero_inventory_from_missing(): void
    {
        $src = (new ReflectionClass(RawImagesBadgeCalculator::class))
            ->getMethod('calculate')
            ->getFileName();
        $code = file_get_contents($src);

        $this->assertStringContainsString('$inv > 0 && ! $hasImage', $code);
        $this->assertStringNotContainsString('$inv > 0 && ! $hasRaw', $code);
    }
}
