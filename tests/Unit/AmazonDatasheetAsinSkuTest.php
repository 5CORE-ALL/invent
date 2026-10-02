<?php

namespace Tests\Unit;

use App\Models\AmazonDatasheet;
use Tests\TestCase;

class AmazonDatasheetAsinSkuTest extends TestCase
{
    public function test_shared_asin_uses_the_sku_spelling_from_the_page(): void
    {
        $older = new AmazonDatasheet(['sku' => 'DP 200 1PCS', 'asin' => 'B0FC81M41C', 'price' => 8.69]);
        $older->id = 91;
        $live = new AmazonDatasheet(['sku' => 'DP 200 1 Pcs', 'asin' => 'B0FC81M41C', 'price' => 5.69]);
        $live->id = 1318;

        $this->assertSame(
            'DP 200 1 Pcs',
            AmazonDatasheet::sellerSkuFromAsinRows([$older, $live], 'DP 200 1 Pcs')
        );
    }

    public function test_shared_asin_without_a_page_sku_keeps_the_oldest_row(): void
    {
        $older = new AmazonDatasheet(['sku' => 'DP 200 1PCS']);
        $older->id = 91;
        $live = new AmazonDatasheet(['sku' => 'DP 200 1 Pcs']);
        $live->id = 1318;

        $this->assertSame(
            'DP 200 1PCS',
            AmazonDatasheet::sellerSkuFromAsinRows([$live, $older], null)
        );
    }
}
