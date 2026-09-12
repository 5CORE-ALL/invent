<?php

namespace Tests\Unit;

use App\Http\Controllers\ShopifyRawDataController;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopifyDirectExclusionsTest extends TestCase
{
    public function test_apply_direct_exclusions_matches_shopify_page_filters(): void
    {
        $query = DB::table('shopify_raw_orders');
        ShopifyRawDataController::applyDirectExclusions($query);

        $sql = strtolower($query->toSql());

        $this->assertStringContainsString('source_name', $sql);
        $this->assertStringContainsString('tags', $sql);
        $this->assertStringContainsString('sku', $sql);
        $this->assertStringContainsString('not like', $sql);
        $this->assertContains('amazon', ShopifyRawDataController::EXCLUDE_SOURCES);
        $this->assertContains('ebay', ShopifyRawDataController::EXCLUDE_SOURCES);
    }
}
