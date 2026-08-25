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
        $this->assertSame('Not Available via Current API', $row['browse_node']);
        $this->assertSame('Not Available via Current API', $row['featured_offer_percentage']);
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
