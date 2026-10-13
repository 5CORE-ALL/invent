<?php

namespace Tests\Unit;

use App\Http\Controllers\AmazonAdsController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AmazonAdsSortPageTest extends TestCase
{
    public function test_campaign_inventory_replaces_missing_name_inv(): void
    {
        $out = $this->withCampaignInventory(
            ['inv' => null, 'ovl30' => null, 'price' => 10],
            ['sum' => 54, 'ovl30' => 12]
        );

        $this->assertSame(54.0, $out['inv']);
        $this->assertSame(12.0, $out['ovl30']);
        $this->assertSame(10, $out['price']);
    }

    public function test_positive_name_inv_is_kept(): void
    {
        $out = $this->withCampaignInventory(
            ['inv' => 8, 'ovl30' => 3],
            ['sum' => 54, 'ovl30' => 12]
        );

        $this->assertSame(8, $out['inv']);
        $this->assertSame(3, $out['ovl30']);
    }

    public function test_suggested_writes_stay_on_the_returned_page(): void
    {
        [$byRow, $byCampaign] = $this->filterSuggested(
            [11 => 1.2, 22 => 0.4, 33 => 0.9],
            ['a' => 1.2, 'b' => 0.4, 'c' => 0.9],
            [
                ['id' => 22, 'campaign_id' => 'b'],
            ]
        );

        $this->assertSame([22 => 0.4], $byRow);
        $this->assertSame(['b' => 0.4], $byCampaign);
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>|null  $skuInv
     * @return array<string, mixed>
     */
    private function withCampaignInventory(array $metrics, ?array $skuInv): array
    {
        $method = new ReflectionMethod(AmazonAdsController::class, 'withCampaignInventory');
        $method->setAccessible(true);

        return $method->invoke(null, $metrics, $skuInv);
    }

    /**
     * @param  array<int|string, mixed>  $byRow
     * @param  array<int|string, mixed>  $byCampaign
     * @param  list<array<string, mixed>>  $page
     * @return array{0: array<int|string, mixed>, 1: array<int|string, mixed>}
     */
    private function filterSuggested(array $byRow, array $byCampaign, array $page): array
    {
        $method = new ReflectionMethod(AmazonAdsController::class, 'filterSuggestedMapsToPage');
        $method->setAccessible(true);

        return $method->invoke(null, $byRow, $byCampaign, $page);
    }
}
