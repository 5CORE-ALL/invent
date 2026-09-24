<?php

namespace Tests\Unit;

use App\Services\FourSellerApiService;
use PHPUnit\Framework\TestCase;

class FourSellerPlatformRefTest extends TestCase
{
    public function test_newegg_reverb_and_aliexpress_order_ids_are_searched(): void
    {
        $refs = FourSellerApiService::searchablePlatformRefs([
            '595123456',
            'newegg-595123456',
            '18439271',
            'reverb-18439271',
            '82109001234613550',
            'aliexpress-82109001234613550',
            '#342860',
            '342860',
        ]);

        $this->assertContains('595123456', $refs);
        $this->assertContains('18439271', $refs);
        $this->assertContains('82109001234613550', $refs);
        $this->assertNotContains('342860', $refs);
        $this->assertNotContains('#342860', $refs);
        $this->assertNotContains('newegg-595123456', $refs);
    }

    public function test_amazon_amz_prefix_is_still_added(): void
    {
        $refs = FourSellerApiService::searchablePlatformRefs(['111-6015771-6213066']);

        $this->assertContains('Amz111-6015771-6213066', $refs);
        $this->assertContains('11160157716213066', $refs);
    }
}
