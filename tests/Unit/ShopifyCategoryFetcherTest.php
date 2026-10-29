<?php

namespace Tests\Unit;

use App\Services\CategoryMasters\ShopifyCategoryFetcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyCategoryFetcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.shopify.store_url' => 'example.myshopify.com',
            'services.shopify.access_token' => 'shpat_test',
        ]);
    }

    public function test_standard_category_wins_and_product_type_is_the_fallback(): void
    {
        Http::fake([
            'https://example.myshopify.com/admin/api/*/graphql.json' => Http::response(['data' => ['productVariants' => ['edges' => [
                ['node' => ['sku' => 'GS EL HYBRID', 'product' => [
                    'productType' => 'Speakers',
                    'category' => ['id' => 'gid://shopify/TaxonomyCategory/el-4-2', 'fullName' => 'Electronics > Audio > Speakers'],
                ]]],
                ['node' => ['sku' => 'mic stand', 'product' => ['productType' => 'Mic Stands', 'category' => null]]],
            ]]]]),
        ]);

        $result = app(ShopifyCategoryFetcher::class)->fetch(['GS EL HYBRID', 'MIC STAND', 'NOT THERE']);

        $this->assertTrue($result['ok']);
        $this->assertSame('Electronics > Audio > Speakers', $result['found']['GS EL HYBRID']['category']);
        $this->assertSame('el-4-2', $result['found']['GS EL HYBRID']['id']);
        $this->assertSame('Mic Stands', $result['found']['MIC STAND']['category']);
        $this->assertSame('', $result['found']['MIC STAND']['id']);
        $this->assertArrayNotHasKey('NOT THERE', $result['found']);
    }

    public function test_a_shopify_error_is_reported_so_nothing_is_marked_as_looked_up(): void
    {
        Http::fake([
            'https://example.myshopify.com/admin/api/*/graphql.json' => Http::response(['errors' => [['message' => 'Throttled']]]),
        ]);

        $result = app(ShopifyCategoryFetcher::class)->fetch(['GS EL HYBRID']);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Throttled', $result['message']);
    }
}
