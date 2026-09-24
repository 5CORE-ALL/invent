<?php

namespace Tests\Unit;

use App\Services\TikTokShopService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class TikTokNewListingRestrictionTest extends TestCase
{
    private const BAN = "12052093: Operation Not Allowed. Cannot create new product listings until '2026-09-24 18:24:21': new listings are temporarily restricted because of previous listing violations. Please save new listing information as a draft or retry after '2026-09-24 18:24:21'.";

    public function test_listing_violation_ban_is_recognized(): void
    {
        $this->assertTrue(TikTokShopService::isNewListingRestrictedError(self::BAN));
        $this->assertTrue(TikTokShopService::isNewListingRestrictedError('Cannot create new product listings until later'));
        $this->assertFalse(TikTokShopService::isNewListingRestrictedError('12052901: Operation Not Allowed. The product must be in one of these statuses: ACTIVATE'));
    }

    public function test_listing_ban_does_not_count_as_a_product_status_error(): void
    {
        $svc = (new ReflectionClass(TikTokShopService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(TikTokShopService::class, 'isProductStatusRestrictionError');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($svc, self::BAN));
        $this->assertTrue($method->invoke(
            $svc,
            '12052901: Operation Not Allowed. The product must be in one of these statuses: ACTIVATE'
        ));
    }
}
