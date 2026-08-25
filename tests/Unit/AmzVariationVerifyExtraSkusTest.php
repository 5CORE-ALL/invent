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
}
