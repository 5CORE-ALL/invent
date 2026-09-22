<?php

namespace Tests\Unit;

use App\Services\AliExpressApiService;
use Tests\TestCase;

class AliExpressListingCategoryTest extends TestCase
{
    public function test_numeric_category_search_does_not_need_api(): void
    {
        $row = AliExpressApiService::listingCategoryRow(200001234, 'Guitar Parts');
        $this->assertSame('200001234', $row['id']);
        $this->assertSame('Guitar Parts', $row['path']);

        $result = (new AliExpressApiService())->searchListingCategories('200001234');
        $this->assertTrue($result['success']);
        $this->assertSame('200001234', $result['categories'][0]['id'] ?? null);
    }

    public function test_empty_query_returns_no_categories(): void
    {
        $result = (new AliExpressApiService())->searchListingCategories('');
        $this->assertTrue($result['success']);
        $this->assertSame([], $result['categories']);
    }
}
