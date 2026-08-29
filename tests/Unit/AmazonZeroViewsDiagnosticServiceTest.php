<?php

namespace Tests\Unit;

use App\Services\AmazonZeroViewsDiagnosticService;
use Tests\TestCase;

class AmazonZeroViewsDiagnosticServiceTest extends TestCase
{
    public function test_inactive_listing_is_blocked(): void
    {
        $row = $this->service()->evaluate([
            'sku' => 'TEST SKU',
            'asin' => 'B0TESTASIN',
            'listing_status' => 'INACTIVE',
            'inventory' => 5,
            'price' => 19.99,
            'title' => 'Test product title',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 0,
            'l7_views' => 0,
        ]);

        $this->assertSame('BLOCKED', $row['diagnostic_status']);
        $this->assertSame('red', $row['color']);
        $this->assertStringContainsString('inactive', strtolower($row['problem']));
    }

    public function test_zero_inventory_is_inventory_issue(): void
    {
        $row = $this->service()->evaluate([
            'sku' => 'TEST SKU',
            'asin' => 'B0TESTASIN',
            'listing_status' => 'ACTIVE',
            'inventory' => 0,
            'price' => 19.99,
            'title' => 'Test product title',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 0,
        ]);

        $this->assertSame('INVENTORY ISSUE', $row['diagnostic_status']);
    }

    public function test_missing_price_is_pricing_issue(): void
    {
        $row = $this->service()->evaluate([
            'sku' => 'TEST SKU',
            'asin' => 'B0TESTASIN',
            'listing_status' => 'ACTIVE',
            'inventory' => 3,
            'price' => null,
            'title' => 'Test product title',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 0,
        ]);

        $this->assertSame('PRICING ISSUE', $row['diagnostic_status']);
    }

    public function test_zero_views_without_rank_is_low_traffic_not_low_ranking(): void
    {
        $row = $this->service()->evaluate([
            'sku' => 'TEST SKU',
            'asin' => 'B0TESTASIN',
            'listing_status' => 'ACTIVE',
            'inventory' => 3,
            'price' => 12.5,
            'title' => 'Test product title',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 0,
            'sales_rank' => null,
        ]);

        $this->assertSame('LOW TRAFFIC', $row['diagnostic_status']);
        $this->assertSame('Not Verified', $row['search_indexed']);
        $this->assertArrayNotHasKey('browse_node', $row);
        $this->assertSame('—', $row['featured_offer_percentage']);
        $this->assertFalse(in_array('BROWSE NODE ISSUE', $row['flags'] ?? [], true));
        $this->assertFalse(collect($row['checkpoints'] ?? [])->contains(fn ($c) => ($c['key'] ?? '') === 'browse_node'));
    }

    public function test_healthy_when_views_exist_and_listing_is_complete(): void
    {
        $row = $this->service()->evaluate([
            'sku' => 'TEST SKU',
            'asin' => 'B0TESTASIN',
            'listing_status' => 'ACTIVE',
            'inventory' => 3,
            'price' => 12.5,
            'title' => 'Test product title',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 40,
            'l7_views' => 8,
        ]);

        $this->assertSame('HEALTHY', $row['diagnostic_status']);
        $this->assertSame('green', $row['color']);
        $this->assertSame('85.5%', $this->service()->evaluate([
            'sku' => 'TEST SKU',
            'asin' => 'B0TESTASIN',
            'listing_status' => 'ACTIVE',
            'inventory' => 3,
            'price' => 12.5,
            'title' => 'Test product title',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 40,
            'buy_box_percentage' => 85.5,
        ])['featured_offer_percentage']);
        $this->assertFalse($row['ad_present']);
        $this->assertTrue($this->service()->evaluate([
            'sku' => 'TEST SKU',
            'asin' => 'B0TESTASIN',
            'listing_status' => 'ACTIVE',
            'inventory' => 3,
            'price' => 12.5,
            'title' => 'Test product title',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 40,
            'ad_present' => true,
        ])['ad_present']);
        $this->assertSame('https://www.amazon.com/dp/B0TESTASIN', $row['buyer_link']);
        $this->assertSame(
            'https://sellercentral.amazon.com/inventory/ref=xx_invmgr_dnav_xx?asin=B0TESTASIN',
            $row['seller_link']
        );
    }

    public function test_standard_amazon_fields_match_analytics_shape(): void
    {
        $row = $this->service()->evaluate([
            'sku' => 'TEST SKU',
            'parent' => '10 FR',
            'asin' => 'B0TESTASIN',
            'listing_status' => 'ACTIVE',
            'inventory' => 20,
            'price' => 25,
            'std_price' => 25,
            'title' => 'Test product title',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 40,
            'l7_views' => 8,
            'ov_l30' => 10,
            'a_l30' => 2,
            'lp' => 5,
            'ship' => 1,
            'lmp_price' => 22.5,
        ]);

        $this->assertSame('10 FR', $row['Parent']);
        $this->assertSame('TEST SKU', $row['(Child) sku']);
        $this->assertSame(20.0, $row['INV']);
        $this->assertSame(10, $row['L30']);
        $this->assertSame(2, $row['A_L30']);
        $this->assertSame(40, $row['Sess30']);
        $this->assertSame(8, $row['Sess7']);
        $this->assertSame(50.0, $row['E Dil%']);
        $this->assertSame(5.0, $row['CVR_L30']);
        $this->assertSame(22.5, $row['lmp_price']);
        $this->assertSame(25.0, $row['price']);
        $this->assertEqualsWithDelta(280.0, $row['GROI%'], 0.01);
        $this->assertSame('TEST SKU', $row['sku']);
    }

    public function test_all_skus_filter_keeps_rows_with_views(): void
    {
        $service = $this->service();
        $withViews = $service->evaluate([
            'sku' => 'VIEWED SKU',
            'asin' => 'B0VIEWED',
            'listing_status' => 'ACTIVE',
            'inventory' => 3,
            'price' => 12.5,
            'title' => 'Viewed product',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 40,
        ]);
        $zeroViews = $service->evaluate([
            'sku' => 'ZERO SKU',
            'asin' => 'B0ZERO',
            'listing_status' => 'ACTIVE',
            'inventory' => 3,
            'price' => 12.5,
            'title' => 'Zero view product',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 0,
        ]);

        $method = new \ReflectionMethod(AmazonZeroViewsDiagnosticService::class, 'matchesComputedFilters');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, $withViews, ['zero_only' => 0, 'l30_views' => 'all']));
        $this->assertTrue($method->invoke($service, $zeroViews, ['zero_only' => 0, 'l30_views' => 'all']));
        $this->assertFalse($method->invoke($service, $withViews, ['zero_only' => 1, 'l30_views' => '0']));
        $this->assertTrue($method->invoke($service, $zeroViews, ['zero_only' => 1, 'l30_views' => '0']));
    }

    public function test_inv_filter_zero_and_more(): void
    {
        $service = $this->service();
        $inStock = $service->evaluate([
            'sku' => 'IN STOCK',
            'asin' => 'B0INSTOCK',
            'listing_status' => 'ACTIVE',
            'inventory' => 71,
            'price' => 12.5,
            'title' => 'In stock product',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 10,
        ]);
        $zeroInv = $service->evaluate([
            'sku' => 'ZERO INV',
            'asin' => 'B0ZEROINV',
            'listing_status' => 'ACTIVE',
            'inventory' => 0,
            'price' => 12.5,
            'title' => 'Zero inventory product',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 10,
        ]);

        $method = new \ReflectionMethod(AmazonZeroViewsDiagnosticService::class, 'matchesComputedFilters');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, $inStock, ['inv' => 'more']));
        $this->assertFalse($method->invoke($service, $zeroInv, ['inv' => 'more']));
        $this->assertFalse($method->invoke($service, $inStock, ['inv' => 'zero']));
        $this->assertTrue($method->invoke($service, $zeroInv, ['inv' => 'zero']));
        $this->assertTrue($method->invoke($service, $inStock, ['inv' => 'all']));
        $this->assertTrue($method->invoke($service, $zeroInv, ['inv' => 'all']));
    }

    public function test_summary_counts_follow_inv_filter_and_do_not_double_count(): void
    {
        $service = $this->service();
        $blockedInStock = $service->evaluate([
            'sku' => 'BLOCKED INV',
            'asin' => 'B0BLOCKED',
            'listing_status' => 'INACTIVE',
            'inventory' => 10,
            'price' => 12.5,
            'title' => 'Blocked in stock',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 5,
        ]);
        $blockedZeroInv = $service->evaluate([
            'sku' => 'BLOCKED ZERO',
            'asin' => 'B0BLOCKED0',
            'listing_status' => 'INACTIVE',
            'inventory' => 0,
            'price' => 12.5,
            'title' => 'Blocked zero inv',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 5,
        ]);
        $suppressed = $service->evaluate([
            'sku' => 'SUPPRESSED',
            'asin' => 'B0SUPP',
            'listing_status' => 'INACTIVE',
            'suppressed' => true,
            'inventory' => 10,
            'price' => 12.5,
            'title' => 'Suppressed product',
            'main_image' => 'https://example.com/a.jpg',
            'category' => 'HOME',
            'l30_views' => 5,
        ]);

        $summarize = new \ReflectionMethod(AmazonZeroViewsDiagnosticService::class, 'summarizeEvaluated');
        $summarize->setAccessible(true);
        $match = new \ReflectionMethod(AmazonZeroViewsDiagnosticService::class, 'matchesComputedFilters');
        $match->setAccessible(true);

        $all = [$blockedInStock, $blockedZeroInv, $suppressed];
        $more = array_values(array_filter(
            $all,
            fn (array $row) => $match->invoke($service, $row, ['inv' => 'more'])
        ));
        $summaryAll = $summarize->invoke($service, $all);
        $summaryMore = $summarize->invoke($service, $more);

        $this->assertSame(3, $summaryAll['total']);
        $this->assertSame(3, $summaryAll['blocked']);
        $this->assertSame(1, $summaryAll['suppressed']);
        $this->assertSame(3, $summaryAll['inactive']);
        $this->assertSame(0, $summaryAll['active']);
        $this->assertSame(3, $summaryAll['low_views']);
        $this->assertSame(2, $summaryMore['blocked']);
        $this->assertSame(1, $summaryMore['suppressed']);
        $this->assertTrue($match->invoke($service, $blockedInStock, ['card' => 'blocked']));
        $this->assertTrue($match->invoke($service, $suppressed, ['card' => 'blocked']));
        $this->assertTrue($match->invoke($service, $suppressed, ['card' => 'suppressed']));
        $this->assertTrue($match->invoke($service, $blockedInStock, ['card' => 'inactive']));
        $this->assertFalse($match->invoke($service, $blockedInStock, ['card' => 'active']));
        $this->assertTrue($match->invoke($service, $blockedInStock, ['card' => 'low_views']));
    }

    public function test_missing_image_is_listing_issue(): void
    {
        $row = $this->service()->evaluate([
            'sku' => 'TEST SKU',
            'asin' => 'B0TESTASIN',
            'listing_status' => 'ACTIVE',
            'inventory' => 3,
            'price' => 12.5,
            'title' => 'Test product title',
            'main_image' => '',
            'category' => 'HOME',
            'l30_views' => 0,
        ]);

        $this->assertSame('LISTING ISSUE', $row['diagnostic_status']);
    }

    private function service(): AmazonZeroViewsDiagnosticService
    {
        return new AmazonZeroViewsDiagnosticService();
    }
}
