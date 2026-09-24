<?php

namespace Tests\Unit;

use App\Services\Amazon\AmazonBidUtilizationService;
use PHPUnit\Framework\TestCase;

class SuggestedSbidStorageTest extends TestCase
{
    public function test_cell_value_is_stored_as_two_decimals(): void
    {
        $this->assertSame('0.45', AmazonBidUtilizationService::suggestedSbidStorageValue(0.4474));
        $this->assertSame('0.75', AmazonBidUtilizationService::suggestedSbidStorageValue('0.75'));
    }

    public function test_blank_suggestion_clears_stored_sbid(): void
    {
        $this->assertNull(AmazonBidUtilizationService::suggestedSbidStorageValue(null));
        $this->assertNull(AmazonBidUtilizationService::suggestedSbidStorageValue(0));
        $this->assertNull(AmazonBidUtilizationService::suggestedSbidStorageValue(''));
    }

    public function test_matching_stored_value_is_not_rewritten(): void
    {
        $want = AmazonBidUtilizationService::suggestedSbidStorageValue(0.45);

        $this->assertTrue(AmazonBidUtilizationService::storedSbidMatches('0.4500', $want));
        $this->assertTrue(AmazonBidUtilizationService::storedSbidMatches(0.45, $want));
        $this->assertFalse(AmazonBidUtilizationService::storedSbidMatches('0.51', $want));
        $this->assertFalse(AmazonBidUtilizationService::storedSbidMatches(null, $want));
        $this->assertTrue(AmazonBidUtilizationService::storedSbidMatches(null, null));
        $this->assertTrue(AmazonBidUtilizationService::storedSbidMatches('0', null));
    }
}
