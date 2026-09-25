<?php

namespace Tests\Unit;

use App\Models\FacebookAllAdsSheet;
use Tests\TestCase;

class FacebookAdTypeNameTest extends TestCase
{
    public function test_builtin_types_include_wholesale_and_dropship(): void
    {
        $this->assertContains('WHOLESALE', FacebookAllAdsSheet::AD_TYPES);
        $this->assertContains('DROPSHIP', FacebookAllAdsSheet::AD_TYPES);
    }

    public function test_normalize_uppercases_and_collapses_spaces(): void
    {
        $this->assertSame('WHOLESALE', FacebookAllAdsSheet::normalizeAdTypeName('  wholeSale  '));
        $this->assertSame('DROP SHIP', FacebookAllAdsSheet::normalizeAdTypeName("drop   ship"));
        $this->assertSame('', FacebookAllAdsSheet::normalizeAdTypeName('   '));
    }
}
