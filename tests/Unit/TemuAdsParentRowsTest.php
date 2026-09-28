<?php

namespace Tests\Unit;

use App\Http\Controllers\Campaigns\TemuAdsController;
use PHPUnit\Framework\TestCase;

class TemuAdsParentRowsTest extends TestCase
{
    public function test_parent_row_sits_above_variation_skus_and_rolls_up_inv(): void
    {
        $rows = TemuAdsController::synthesizeParentAdsRows([
            [
                'id' => 1,
                'goods_id' => '610036935935062',
                'parent' => 'DJ RACK',
                'sku' => 'RACK STAND 9U',
                'sku_id' => 'a',
                'inv' => 2,
                'ovl30' => 1,
                'all_sale' => 10.0,
                'period' => 'L30',
                'ad_spend' => 5,
                'image_path' => '/img/9u.jpg',
            ],
            [
                'id' => 2,
                'goods_id' => '610036935935062',
                'parent' => 'DJ RACK',
                'sku' => 'RACK STAND 12U',
                'sku_id' => 'b',
                'inv' => 3,
                'ovl30' => 0,
                'all_sale' => 0.0,
                'period' => 'L30',
                'ad_spend' => 5,
                'image_path' => '/img/12u.jpg',
            ],
            [
                'id' => 3,
                'goods_id' => '610036935935062',
                'parent' => 'DJ RACK',
                'sku' => 'RACK STAND 16U',
                'sku_id' => 'c',
                'inv' => 1,
                'ovl30' => 2,
                'all_sale' => 20.0,
                'period' => 'L30',
                'ad_spend' => 5,
                'image_path' => '/img/16u.jpg',
            ],
            [
                'id' => 4,
                'goods_id' => '999',
                'parent' => '',
                'sku' => 'SOLO',
                'sku_id' => 'd',
                'inv' => 1,
                'ovl30' => 0,
                'all_sale' => 0.0,
                'period' => 'L30',
                'ad_spend' => 1,
                'image_path' => null,
            ],
        ], ['PARENT DJ RACK' => '/img/parent.jpg']);

        $this->assertSame([
            'SOLO',
            'PARENT DJ RACK',
            'RACK STAND 12U',
            'RACK STAND 16U',
            'RACK STAND 9U',
        ], $rows->pluck('sku')->all());

        $parent = $rows->firstWhere('is_parent', true);
        $this->assertTrue($parent['is_parent']);
        $this->assertSame('p-610036935935062-L30', $parent['id']);
        $this->assertSame(1, $parent['raw_id']);
        $this->assertSame('DJ RACK', $parent['parent']);
        $this->assertSame('', $parent['sku_id']);
        $this->assertSame(6, $parent['inv']);
        $this->assertSame(3, $parent['ovl30']);
        $this->assertSame(50.0, $parent['dil_percent']);
        $this->assertSame(30.0, $parent['all_sale']);
        $this->assertSame(5, $parent['ad_spend']);
        $this->assertSame('/img/parent.jpg', $parent['image_path']);
        $this->assertSame(1, $rows->where('is_parent', true)->count());
    }

    public function test_create_reject_is_cleared_when_the_ad_already_exists(): void
    {
        $rows = TemuAdsController::blankCreateRejectsForExistingAds([
            [
                'goods_id' => '602475553743331',
                'sku' => 'CONGO 3 BLK',
                'ad_status' => 'Active',
                'ad_create_reject' => 'The value entered for the daily budget must be between 11 and 999,999',
            ],
            [
                'goods_id' => '602475553743331',
                'sku' => 'CONGO 3 BLU',
                'ad_status' => 'No ad',
                'ad_create_reject' => 'The value entered for the daily budget must be between 11 and 999,999',
            ],
            [
                'goods_id' => '111',
                'sku' => 'STILL MISSING',
                'ad_status' => 'No ad',
                'ad_create_reject' => 'listing not eligible',
            ],
        ]);

        $this->assertSame('', $rows->firstWhere('sku', 'CONGO 3 BLK')['ad_create_reject']);
        $this->assertSame('', $rows->firstWhere('sku', 'CONGO 3 BLU')['ad_create_reject']);
        $this->assertSame('listing not eligible', $rows->firstWhere('sku', 'STILL MISSING')['ad_create_reject']);
    }
}
