<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\AmazonListingPublishService;
use PHPUnit\Framework\TestCase;

class AmazonListingPublishBrandTest extends TestCase
{
    public function test_uses_app_brand_instead_of_inc_suffix(): void
    {
        $this->assertSame('5 Core', AmazonListingPublishService::displayBrand([
            'brand' => '5 Core Inc.',
        ]));
        $this->assertSame('5 Core', AmazonListingPublishService::displayBrand([
            'vendor' => '5 Core',
        ]));
        $this->assertSame('5 Core', AmazonListingPublishService::displayManufacturer([
            'manufacturer' => '5 Core Inc',
        ]));
    }

    public function test_strips_inc_suffix(): void
    {
        $this->assertSame('5 Core', AmazonListingPublishService::withoutIncSuffix('5 Core Inc.'));
        $this->assertSame('5 Core', AmazonListingPublishService::withoutIncSuffix('5 Core'));
    }
}
