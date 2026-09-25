<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketPlace\AmzVariationVerifyController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class AmzVariationVerifyExtraSkusTest extends TestCase
{
    public function test_ds_vel_does_not_inherit_unrelated_prefix_campaigns(): void
    {
        $children = [
            ['sku' => 'DS CH BLK VEL HD'],
            ['sku' => 'DS CH BLU VEL HD'],
            ['sku' => 'DS CH D-GR VEL HD'],
            ['sku' => 'DS CH ORG VEL HD'],
            ['sku' => 'DS CH RED VEL HD'],
            ['sku' => 'DS CH YLW VEL HD'],
        ];

        $allParents = [
            'DS VEL',
            'DS VEL REST LVR',
            'DS VEL REST SWL',
            'DS SDL',
            'DS CH REST LVR',
        ];

        $kwBases = [
            'PARENT DS VEL',
            'DS CH BLK VEL HD',
            'DS CH WH SDL',
            'DS VEL RED REST-LVR',
            'PARENT DS VEL REST LVR LT',
            'PARENT DS VEL REST LVR',
        ];

        $ptBases = [
            'PARENT DS VEL',
            'DS CH WH SDL',
            'DS VEL RED REST-LVR',
            'PARENT DS VEL REST LVR',
        ];

        $this->assertSame([], $this->extras('DS VEL', $children, $kwBases, $allParents));
        $this->assertSame([], $this->extras('DS VEL', $children, $ptBases, $allParents));
    }

    public function test_true_parent_suffix_campaign_is_still_extra(): void
    {
        $extras = $this->extras(
            'DS VEL',
            [['sku' => 'DS CH BLK VEL HD']],
            ['PARENT DS VEL', 'PARENT DS VEL RANDOM SKU'],
            ['DS VEL', 'DS VEL REST LVR']
        );

        $this->assertSame(['DS VEL RANDOM SKU'], $extras);
    }

    public function test_parent_dt_kw_product_ad_is_keyword_not_product_target(): void
    {
        $this->assertSame('kw', $this->adType('PARENT DT KW'));
        $this->assertSame('pt', $this->adType('PARENT DT PT'));
        $this->assertNull($this->adType('PARENT DT HEAD'));
        $this->assertNull($this->adType('PIANO BENCH HL'));
        $this->assertNull($this->adType(''));
    }

    public function test_advertised_sku_in_parent_dt_kw_is_not_missing(): void
    {
        $controller = (new ReflectionClass(AmzVariationVerifyController::class))
            ->newInstanceWithoutConstructor();
        $remember = new ReflectionMethod(AmzVariationVerifyController::class, 'rememberAdvertisedSku');
        $remember->setAccessible(true);

        $bucket = [
            'kw' => [],
            'pt' => [],
            'kw_campaigns' => [],
            'pt_campaigns' => [],
        ];
        $remember->invokeArgs($controller, [&$bucket, 'PNB DT HT PNK', 'PARENT DT KW']);
        $remember->invokeArgs($controller, [&$bucket, "PNB\u{00A0}DT\u{00A0}BLK", 'PARENT DT PT']);

        $this->assertArrayHasKey('PNBDTHTPNK', $bucket['kw']);
        $this->assertArrayHasKey('PNB DT HT PNK', $bucket['kw']);
        $this->assertArrayNotHasKey('PNBDTHTPNK', $bucket['pt']);
        $this->assertSame(['PARENT DT KW' => true], $bucket['kw_campaigns']['PNB DT HT PNK']);
        $this->assertArrayHasKey('PNBDTBLK', $bucket['pt']);
        $this->assertArrayNotHasKey('PNBDTBLK', $bucket['kw']);

        $has = new ReflectionMethod(AmzVariationVerifyController::class, 'skuHasCampaignType');
        $has->setAccessible(true);
        $lookup = [
            'empty' => false,
            'kw_keys' => [],
            'pt_keys' => [],
            'kw_parent_keys' => [],
            'pt_parent_keys' => [],
            'kw_product_ad_skus' => $bucket['kw'],
            'pt_product_ad_skus' => $bucket['pt'],
        ];

        $this->assertTrue($has->invoke($controller, 'PNB DT HT PNK', 'PNB DT', true, $lookup, 'kw'));
        $this->assertFalse($has->invoke($controller, 'PNB DT HT PNK', 'PNB DT', true, $lookup, 'pt'));
    }

    public function test_product_ad_is_kept_when_campaign_title_does_not_match_parent(): void
    {
        $controller = (new ReflectionClass(AmzVariationVerifyController::class))
            ->newInstanceWithoutConstructor();
        $store = new ReflectionMethod(AmzVariationVerifyController::class, 'storeAdvertisedProductAd');
        $store->setAccessible(true);
        $resolve = new ReflectionMethod(AmzVariationVerifyController::class, 'resolveAdvertisedAdType');
        $resolve->setAccessible(true);

        $this->assertSame('kw', $resolve->invoke($controller, '451749491562952', '', [
            '451749491562952' => 'kw',
        ]));
        $this->assertSame('pt', $resolve->invoke($controller, '338326385248126', 'PARENT DT KW', [
            '338326385248126' => 'pt',
        ]));

        $bucket = [
            'kw' => [],
            'pt' => [],
            'kw_campaigns' => [],
            'pt_campaigns' => [],
        ];
        $store->invokeArgs($controller, [&$bucket, 'PNB DT HT PNK', '', 'kw']);
        $this->assertArrayHasKey('PNBDTHTPNK', $bucket['kw']);
        $this->assertArrayNotHasKey('PNBDTHTPNK', $bucket['pt']);

        $headline = [
            'kw' => [],
            'pt' => [],
            'kw_campaigns' => [],
            'pt_campaigns' => [],
        ];
        $store->invokeArgs($controller, [&$headline, 'PNB DT HT PNK', 'PIANO BENCH HL', null]);
        $this->assertSame([], $headline['kw']);
        $this->assertSame([], $headline['pt']);

        $unknown = [
            'kw' => [],
            'pt' => [],
            'kw_campaigns' => [],
            'pt_campaigns' => [],
        ];
        $store->invokeArgs($controller, [&$unknown, 'PNB DT HT PNK', '', null]);
        $this->assertArrayHasKey('PNBDTHTPNK', $unknown['kw']);
        $this->assertArrayHasKey('PNBDTHTPNK', $unknown['pt']);
    }

    public function test_added_product_ad_counts_as_in_campaign_when_unlisted(): void
    {
        $controller = (new ReflectionClass(AmzVariationVerifyController::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AmzVariationVerifyController::class, 'skuHasCampaignType');
        $method->setAccessible(true);

        $lookup = [
            'empty' => false,
            'kw_keys' => [],
            'pt_keys' => [],
            'kw_parent_keys' => ['MUS FLD HD ACC' => true],
            'pt_parent_keys' => ['MUS FLD HD ACC' => true],
            'kw_product_ad_skus' => ['MUSFLDHDACCBLKTRAY' => true],
            'pt_product_ad_skus' => [],
        ];

        $this->assertTrue($method->invoke(
            $controller,
            'MUS FLD HD ACC BLK TRAY',
            'MUS FLD HD ACC',
            false,
            $lookup,
            'kw'
        ));
        $this->assertFalse($method->invoke(
            $controller,
            'MUS FLD HD ACC BLK TRAY',
            'MUS FLD HD ACC',
            false,
            $lookup,
            'pt'
        ));
    }

    public function test_longer_parent_owns_shared_prefix_campaign(): void
    {
        $allParents = ['DS VEL', 'DS VEL REST LVR'];

        $this->assertSame(
            [],
            $this->extras('DS VEL', [['sku' => 'DS CH BLK VEL HD']], ['PARENT DS VEL REST LVR LT'], $allParents)
        );
        $this->assertSame(
            ['DS VEL REST LVR LT'],
            $this->extras('DS VEL REST LVR', [['sku' => 'DS CH BLK-VEL REST LVR']], ['PARENT DS VEL REST LVR LT'], $allParents)
        );
    }

    /**
     * @param  list<array{sku: string}>  $children
     * @param  list<string>  $campaignBases
     * @param  list<string>  $allParentKeys
     * @return list<string>
     */
    private function extras(string $parent, array $children, array $campaignBases, array $allParentKeys): array
    {
        $controller = (new ReflectionClass(AmzVariationVerifyController::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AmzVariationVerifyController::class, 'findExtraAdSkus');
        $method->setAccessible(true);

        return $method->invoke(
            $controller,
            $parent,
            $children,
            $campaignBases,
            [],
            $allParentKeys
        );
    }

    private function adType(string $campaignName): ?string
    {
        $controller = (new ReflectionClass(AmzVariationVerifyController::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AmzVariationVerifyController::class, 'campaignReportAdType');
        $method->setAccessible(true);

        return $method->invoke($controller, $campaignName);
    }
}
