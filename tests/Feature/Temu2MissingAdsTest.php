<?php

namespace Tests\Feature;

use App\Http\Controllers\Campaigns\Temu2MissingAdsController;
use App\Models\ShopifySku;
use App\Models\Temu2CampaignReport;
use App\Models\Temu2Metric;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Temu2MissingAdsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\CloseDbConnections::class);
    }

    private function actingUser(): User
    {
        $user = User::query()->first();
        if ($user) {
            return $user;
        }

        return User::factory()->create();
    }

    public function test_guest_is_redirected_from_missing_ads_page(): void
    {
        $this->get(route('temu2.ads.missing', [], false))
            ->assertRedirect();
    }

    public function test_page_renders_temu_2_missing_ads_title_and_create_controls(): void
    {
        $this->actingAs($this->actingUser());

        $html = $this->get(route('temu2.ads.missing', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Temu 2 Missing Ads', $html);
        $this->assertStringContainsString('Create ads Rule', $html);
        $this->assertStringContainsString('create-bulk', $html);
        $this->assertStringContainsString('Missing:', $html);
    }

    public function test_data_includes_only_no_ad_rows_with_inventory(): void
    {
        if (! Schema::hasTable('temu2_campaign_reports') || ! Schema::hasTable('shopify_skus')) {
            $this->markTestSkipped('Temu 2 ads or Shopify SKU tables are missing.');
        }

        $this->actingAs($this->actingUser());
        $suffix = str_replace('.', '', uniqid('t2m', true));

        $includeSku = 'T2MISS INC '.$suffix;
        $zeroSku = 'T2MISS ZERO '.$suffix;
        $activeSku = 'T2MISS ACT '.$suffix;
        $dupSku = 'T2MISS DUP '.$suffix;
        $metricSku = 'T2MISS MET '.$suffix;

        $includeGid = '8001'.$suffix;
        $zeroGid = '8002'.$suffix;
        $activeGid = '8003'.$suffix;
        $dupGid = '8004'.$suffix;
        $metricGid = '8005'.$suffix;

        $nextShopifyId = ((int) ShopifySku::query()->max('id')) + 1;
        ShopifySku::query()->insert([
            ['id' => $nextShopifyId, 'sku' => $includeSku, 'inv' => 8, 'quantity' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextShopifyId + 1, 'sku' => $zeroSku, 'inv' => 0, 'quantity' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextShopifyId + 2, 'sku' => $activeSku, 'inv' => 12, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextShopifyId + 3, 'sku' => $dupSku, 'inv' => 4, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextShopifyId + 4, 'sku' => $metricSku, 'inv' => 6, 'quantity' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $nextReportId = ((int) Temu2CampaignReport::query()->max('id')) + 1;
        Temu2CampaignReport::query()->insert([
            ['id' => $nextReportId, 'goods_id' => $includeGid, 'sku' => $includeSku, 'report_range' => 'L30', 'status' => 'Not Created', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextReportId + 1, 'goods_id' => $zeroGid, 'sku' => $zeroSku, 'report_range' => 'L30', 'status' => 'No ad', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextReportId + 2, 'goods_id' => $activeGid, 'sku' => $activeSku, 'report_range' => 'L30', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextReportId + 3, 'goods_id' => $dupGid, 'sku' => $dupSku, 'report_range' => 'L30', 'status' => 'Not Created', 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextReportId + 4, 'goods_id' => $dupGid, 'sku' => $dupSku, 'report_range' => 'L7', 'status' => 'Not Created', 'created_at' => now(), 'updated_at' => now()],
        ]);

        if (Schema::hasTable('temu2_metrics')) {
            Temu2Metric::query()->insert([
                'id' => ((int) Temu2Metric::query()->max('id')) + 1,
                'sku' => $metricSku,
                'goods_id' => $metricGid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Temu2MissingAdsController::forgetMissingTotalCache();

        $response = $this->getJson(route('temu2.ads.missing.data', [], false));
        $response->assertOk()->assertJsonStructure(['data', 'total']);

        $goodsIds = collect($response->json('data'))->pluck('goods_id')->map(fn ($id) => (string) $id);

        $this->assertTrue($goodsIds->contains($includeGid), 'In-stock No ad goods should be missing');
        $this->assertFalse($goodsIds->contains($zeroGid), 'Zero inventory should not be missing');
        $this->assertFalse($goodsIds->contains($activeGid), 'Active ads should not be missing');
        $this->assertSame(1, $goodsIds->filter(fn ($id) => $id === $dupGid)->count(), 'Duplicate period rows should count once');
        if (Schema::hasTable('temu2_metrics')) {
            $this->assertTrue($goodsIds->contains($metricGid), 'Temu 2 goods with no ads report should be missing');
        }

        $includeRow = collect($response->json('data'))->firstWhere('goods_id', $includeGid);
        $this->assertSame('No ad', $includeRow['ad_status'] ?? null);
        $this->assertGreaterThan(0, (int) ($includeRow['inv'] ?? 0));

        $this->assertSame(
            (int) $response->json('total'),
            Temu2MissingAdsController::missingTotalCount()
        );
    }
}
