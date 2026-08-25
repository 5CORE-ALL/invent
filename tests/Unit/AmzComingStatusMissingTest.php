<?php

namespace Tests\Unit;

use App\Http\Controllers\MarketPlace\AmzListingVariationVerifyController;
use App\Http\Controllers\MarketPlace\AmzVariationVerifyController;
use App\Models\ProductMaster;
use App\Support\Marketplace\AmazonAdsMissingLinks;
use App\Support\Marketplace\AmazonListingCounts;
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
        $this->assertSame('Active', ProductMaster::statusDisplayLabel('active'));
        $this->assertSame('Coming', ProductMaster::statusDisplayLabel('upcoming'));
        $this->assertSame('DC', ProductMaster::statusDisplayLabel('dc'));
        $this->assertSame('2BDC', ProductMaster::statusDisplayLabel('2BDC'));
    }

    public function test_parent_status_rollups_unique_child_labels(): void
    {
        [$controller, $method] = $this->adsMethod('rollupChildProductMasterStatus');
        $rollup = $method->invoke($controller, [
            ['pm_status' => 'active', 'pm_status_label' => 'Active'],
            ['pm_status' => 'upcoming', 'pm_status_label' => 'Coming'],
            ['pm_status' => 'active', 'pm_status_label' => 'Active'],
        ]);

        $this->assertSame('Coming', ProductMaster::statusDisplayLabel('upcoming'));
        $this->assertSame('active,upcoming', $rollup['pm_status']);
        $this->assertSame('Active, Coming', $rollup['pm_status_label']);
    }

    public function test_coming_child_is_not_missing_on_kw_or_pt(): void
    {
        [$controller, $method] = $this->adsMethod('buildSiblingAdFields');

        $fields = $method->invoke($controller, false, false, false, true);
        $this->assertFalse($fields['missing']);
        $this->assertSame('coming', $fields['status']);
        $this->assertSame('Coming', $fields['label']);

        $activeMissing = $method->invoke($controller, false, true, false, false, false);
        $this->assertTrue($activeMissing['missing']);
        $this->assertSame('missing', $activeMissing['status']);
    }

    public function test_low_inv_child_is_not_missing_on_kw_or_pt(): void
    {
        [$controller, $method] = $this->adsMethod('buildSiblingAdFields');

        $fields = $method->invoke($controller, false, true, false, false, true);
        $this->assertFalse($fields['missing']);
        $this->assertSame('low_inv', $fields['status']);
        $this->assertSame('INV ≤1', $fields['label']);
    }

    public function test_amazon_nrl_child_is_not_missing_on_kw_or_pt(): void
    {
        [$controller, $method] = $this->adsMethod('buildSiblingAdFields');

        $fields = $method->invoke($controller, false, false, false, false, false, true);
        $this->assertFalse($fields['missing']);
        $this->assertSame('nrl', $fields['status']);
        $this->assertSame('NRL', $fields['label']);

        $this->assertTrue(AmazonListingCounts::isNrl(['NRL' => 'NRL']));
        $this->assertTrue(AmazonListingCounts::isNrl(['NR' => 'NR']));
        $this->assertTrue(AmazonListingCounts::isNrl('NRL'));
        $this->assertFalse(AmazonListingCounts::isNrl(['NRL' => 'REQ']));
        $this->assertFalse(AmazonListingCounts::isNrl(['NRL' => 'RL']));
        $this->assertTrue(AmazonListingCounts::skuIsNrl('SKU NRL', ['SKUNRL' => true]));
        $this->assertTrue(AmazonListingCounts::skuIsNrl('SKU  NRL', ['SKU NRL' => true]));
        $this->assertTrue(AmazonListingCounts::skuIsNrl('SKU NRL', ['SKUNRL' => true, 'SKU NRL' => true]));
        $this->assertFalse(AmazonListingCounts::skuIsNrl('SKU ACTIVE', ['SKUNRL' => true]));
        $this->assertContains('SKU NRL', AmazonListingCounts::skuLookupKeys('SKU  NRL'));
        $this->assertContains('SKUNRL', AmazonListingCounts::skuLookupKeys('SKU NRL FBA'));
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
                'skip_ads_required' => true,
                'kw_existing' => false,
                'kw_missing' => false,
                'kw_over' => false,
                'kw_campaign_names' => [],
            ],
            [
                'sku' => 'SKU LOW INV',
                'is_coming' => false,
                'is_low_inv' => true,
                'skip_ads_required' => true,
                'kw_existing' => false,
                'kw_missing' => false,
                'kw_over' => false,
                'kw_campaign_names' => [],
            ],
            [
                'sku' => 'SKU NRL',
                'is_coming' => false,
                'is_nrl' => true,
                'skip_ads_required' => true,
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
                $row = [
                    'sku' => $child['sku'],
                    'is_coming' => $child['is_coming'],
                    'is_low_inv' => ! empty($child['is_low_inv']),
                    'is_nrl' => ! empty($child['is_nrl']),
                    'skip_ads_required' => ! empty($child['skip_ads_required']),
                ];
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
            $this->assertNotContains('SKU LOW INV', $rollup['missing_skus']);
            $this->assertNotContains('SKU NRL', $rollup['missing_skus']);
        }
    }

    public function test_listing_parent_does_not_count_coming_as_missing(): void
    {
        [$controller, $method] = $this->listingMethod('diffParentListing');
        $children = [
            ['sku' => 'SKU ACTIVE', 'child_sku_available' => true, 'is_coming' => false],
            ['sku' => 'SKU COMING', 'child_sku_available' => false, 'is_coming' => true],
            ['sku' => 'SKU NRL', 'child_sku_available' => false, 'is_nrl' => true, 'skip_listing_required' => true],
            ['sku' => 'SKU ZERO INV', 'child_sku_available' => false, 'is_zero_inv' => true, 'skip_listing_required' => true],
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
        $this->assertNotContains('SKU NRL', $diff['missing_skus']);
        $this->assertNotContains('SKU ZERO INV', $diff['missing_skus']);
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

    public function test_amazon_ads_missing_link_helpers_match_missing_page(): void
    {
        $this->assertSame('PARENT DEMO PARENT', AmazonAdsMissingLinks::skuForParent('DEMO PARENT'));
        $this->assertSame('PARENT DEMO PARENT', AmazonAdsMissingLinks::skuForParent('  DEMO   PARENT  '));
        $this->assertSame('PARENT DEMO KW', AmazonAdsMissingLinks::normalizeCampaignName('parent demo kw.'));

        $links = collect([
            (object) ['id' => 1, 'type' => 'KW', 'campaign_id' => '11', 'campaign_name' => 'PARENT DEMO KW'],
            (object) ['id' => 2, 'type' => 'PT', 'campaign_id' => '22', 'campaign_name' => 'PARENT DEMO PT'],
        ]);
        $statusMap = ['PARENT DEMO KW' => 'ENABLED', 'PARENT DEMO PT' => 'PAUSED'];

        $kw = AmazonAdsMissingLinks::linkListForType($links, 'KW', $statusMap);
        $pt = AmazonAdsMissingLinks::linkListForType($links, 'PT', $statusMap);

        $this->assertCount(1, $kw);
        $this->assertSame('PARENT DEMO KW', $kw[0]['campaign_name']);
        $this->assertSame('green', $kw[0]['dot']);
        $this->assertCount(1, $pt);
        $this->assertSame('red', $pt[0]['dot']);
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
