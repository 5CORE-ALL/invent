<?php

namespace Tests\Unit;

use App\Support\AmazonAdsCampaignSkuMetrics;
use App\Support\AmazonAdsCampaignSkuSync;
use PHPUnit\Framework\TestCase;

class AmazonAdsCampaignSkuSyncTest extends TestCase
{
    public function test_sku_key_strips_head_suffix(): void
    {
        $this->assertSame(
            'PARENT MUS FLD HD ACC',
            AmazonAdsCampaignSkuMetrics::skuKeyFromCampaignName('PARENT MUS FLD HD ACC HEAD')
        );
    }

    public function test_sku_key_strips_kw_and_hl(): void
    {
        $this->assertSame('ABC 123', AmazonAdsCampaignSkuMetrics::skuKeyFromCampaignName('ABC 123 KW'));
        $this->assertSame('ABC 123', AmazonAdsCampaignSkuMetrics::skuKeyFromCampaignName('ABC 123 HL'));
    }

    public function test_name_ad_id_stays_short(): void
    {
        $id = AmazonAdsCampaignSkuSync::nameAdId('12345', 'SHORT-SKU');
        $this->assertSame('name:12345:SHORT-SKU', $id);

        $long = str_repeat('X', 300);
        $hashed = AmazonAdsCampaignSkuSync::nameAdId('12345', $long);
        $this->assertTrue(str_starts_with($hashed, 'name:12345:'));
        $this->assertLessThanOrEqual(190, strlen($hashed));
    }

    public function test_extract_asins_from_sb_creative(): void
    {
        $asins = AmazonAdsCampaignSkuSync::extractAsinsFromSbAd([
            'adId' => '99',
            'creative' => [
                'asins' => ['B0CKPMCDWW', 'b0dzctkgsn'],
                'landingPage' => ['asin' => 'B0ABCDEF12'],
            ],
        ]);
        sort($asins);
        $this->assertSame(['B0ABCDEF12', 'B0CKPMCDWW', 'B0DZCTKGSN'], $asins);
    }
}
