<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketPlace\AmzListingVariationVerifyController;
use App\Http\Controllers\MarketPlace\AmzVariationVerifyController;
use App\Models\ProductMaster;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class AmzComingStatusMissingTest extends TestCase
{
    public function test_product_master_coming_aliases(): void
    {
        $this->assertTrue(ProductMaster::isComingStatus('upcoming'));
        $this->assertTrue(ProductMaster::isComingStatus('Coming'));
        $this->assertTrue(ProductMaster::isComingStatus('Comming'));
        $this->assertTrue(ProductMaster::isComingStatus(['status' => 'upcoming']));
        $this->assertFalse(ProductMaster::isComingStatus('active'));
        $this->assertFalse(ProductMaster::isComingStatus(['status' => 'DC']));
        $this->assertFalse(ProductMaster::isComingStatus([]));
    }

    public function test_coming_child_is_not_missing_on_kw_or_pt(): void
    {
        [$controller, $method] = $this->adsMethod('buildSiblingAdFields');

        $fields = $method->invoke($controller, false, false, false, true);
        $this->assertFalse($fields['missing']);
        $this->assertSame('coming', $fields['status']);
        $this->assertSame('Coming', $fields['label']);

        $activeMissing = $method->invoke($controller, false, true, false, false);
        $this->assertTrue($activeMissing['missing']);
        $this->assertSame('missing', $activeMissing['status']);
    }

    public function test_coming_child_already_in_campaign_stays_added(): void
    {
        [$controller, $method] = $this->adsMethod('buildSiblingAdFields');
        $fields = $method->invoke($controller, true, true, false, true);

        $this->assertFalse($fields['missing']);
        $this->assertTrue($fields['existing']);
        $this->assertSame('added', $fields['status']);
    }

    public function test_sibling_rollup_excludes_coming_from_required_and_missing(): void
    {
        [$controller, $method] = $this->adsMethod('rollupSiblingAds');
        $children = [
            [
                'sku' => 'SKU ACTIVE',
                'is_coming' => false,
                'kw_existing' => true,
                'kw_missing' => false,
                'kw_over' => false,
                'kw_campaign_names' => ['PARENT DEMO KW'],
            ],
            [
                'sku' => 'SKU COMING',
                'is_coming' => true,
                'kw_existing' => false,
                'kw_missing' => false,
                'kw_over' => false,
                'kw_campaign_names' => [],
            ],
            [
                'sku' => 'SKU MISSING',
                'is_coming' => false,
                'kw_existing' => false,
                'kw_missing' => true,
                'kw_over' => false,
                'kw_campaign_names' => [],
            ],
        ];

        foreach (['kw', 'pt'] as $type) {
            $typed = [];
            foreach ($children as $child) {
                $row = ['sku' => $child['sku'], 'is_coming' => $child['is_coming']];
                foreach (['existing', 'missing', 'over', 'campaign_names'] as $key) {
                    $row[$type.'_'.$key] = $child['kw_'.$key];
                }
                $typed[] = $row;
            }

            $rollup = $method->invoke($controller, $typed, $type, false, [], []);

            $this->assertSame(2, $rollup['required'], $type.' required');
            $this->assertSame(1, $rollup['existing'], $type.' existing');
            $this->assertSame(1, $rollup['missing'], $type.' missing');
            $this->assertSame(['SKU MISSING'], $rollup['missing_skus']);
            $this->assertNotContains('SKU COMING', $rollup['missing_skus']);
        }
    }

    public function test_listing_parent_does_not_count_coming_as_missing(): void
    {
        [$controller, $method] = $this->listingMethod('diffParentListing');
        $children = [
            ['sku' => 'SKU ACTIVE', 'child_sku_available' => true, 'is_coming' => false],
            ['sku' => 'SKU COMING', 'child_sku_available' => false, 'is_coming' => true],
            ['sku' => 'SKU MISSING', 'child_sku_available' => false, 'is_coming' => false],
        ];
        $lookup = [
            'empty' => false,
            'set' => ['SKUACTIVE' => true],
            'sku_to_listed' => ['SKUACTIVE' => 'SKU ACTIVE'],
        ];

        $diff = $method->invoke($controller, 'DEMO PARENT', $children, $lookup, []);

        $this->assertTrue($diff['known']);
        $this->assertSame(1, $diff['available_count']);
        $this->assertSame(['SKU MISSING'], $diff['missing_skus']);
        $this->assertNotContains('SKU COMING', $diff['missing_skus']);
    }

    public function test_listed_coming_sku_is_not_an_extra(): void
    {
        [$controller, $method] = $this->listingMethod('diffParentListing');
        $children = [
            ['sku' => 'SKU ACTIVE', 'child_sku_available' => true, 'is_coming' => false],
            ['sku' => 'SKU COMING', 'child_sku_available' => true, 'is_coming' => true],
        ];
        $lookup = [
            'empty' => false,
            'set' => ['SKUACTIVE' => true, 'SKUCOMING' => true],
            'sku_to_listed' => [
                'SKUACTIVE' => 'SKU ACTIVE',
                'SKUCOMING' => 'SKU COMING',
            ],
        ];

        $diff = $method->invoke($controller, 'DEMO PARENT', $children, $lookup, []);

        $this->assertSame(1, $diff['available_count']);
        $this->assertSame([], $diff['missing_skus']);
        $this->assertSame([], $diff['extra_skus']);
    }

    /**
     * @return array{0: object, 1: ReflectionMethod}
     */
    private function adsMethod(string $name): array
    {
        $controller = (new ReflectionClass(AmzVariationVerifyController::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AmzVariationVerifyController::class, $name);
        $method->setAccessible(true);

        return [$controller, $method];
    }

    /**
     * @return array{0: object, 1: ReflectionMethod}
     */
    private function listingMethod(string $name): array
    {
        $controller = (new ReflectionClass(AmzListingVariationVerifyController::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AmzListingVariationVerifyController::class, $name);
        $method->setAccessible(true);

        return [$controller, $method];
    }
}
