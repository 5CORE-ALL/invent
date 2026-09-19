<?php

namespace Tests\Feature;

use App\Http\Controllers\Campaigns\Temu1MissingAdsController;
use App\Models\ShopifySku;
use App\Models\TemuAdsApiReport;
use App\Models\TemuMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Temu1MissingAdsTest extends TestCase
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
        $this->get(route('temu.ads.missing', [], false))
            ->assertRedirect();
    }

    public function test_page_renders_temu_1_missing_ads_title_and_create_controls(): void
    {
        $this->actingAs($this->actingUser());

        $html = $this->get(route('temu.ads.missing', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Temu 1 Missing Ads', $html);
        $this->assertStringContainsString('Create ads Rule', $html);
        $this->assertStringContainsString('create-bulk', $html);
        $this->assertStringContainsString('Missing:', $html);
    }

    public function test_data_includes_only_no_ad_rows_with_inventory(): void
    {
        if (! Schema::hasTable('temu_ads_api_reports') || ! Schema::hasTable('shopify_skus')) {
            $this->markTestSkipped('Temu ads or Shopify SKU tables are missing.');
        }

        $this->actingAs($this->actingUser());
        $suffix = str_replace('.', '', uniqid('t1m', true));

        $includeSku = 'T1MISS INC '.$suffix;
        $zeroSku = 'T1MISS ZERO '.$suffix;
        $activeSku = 'T1MISS ACT '.$suffix;
        $dupSku = 'T1MISS DUP '.$suffix;
        $metricSku = 'T1MISS MET '.$suffix;

        $includeGid = '9001'.$suffix;
        $zeroGid = '9002'.$suffix;
        $activeGid = '9003'.$suffix;
        $dupGid = '9004'.$suffix;
        $metricGid = '9005'.$suffix;

        $nextShopifyId = ((int) ShopifySku::query()->max('id')) + 1;
        ShopifySku::query()->insert([
            ['id' => $nextShopifyId, 'sku' => $includeSku, 'inv' => 8, 'quantity' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextShopifyId + 1, 'sku' => $zeroSku, 'inv' => 0, 'quantity' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextShopifyId + 2, 'sku' => $activeSku, 'inv' => 12, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextShopifyId + 3, 'sku' => $dupSku, 'inv' => 4, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextShopifyId + 4, 'sku' => $metricSku, 'inv' => 6, 'quantity' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $nextReportId = ((int) TemuAdsApiReport::query()->max('id')) + 1;
        TemuAdsApiReport::query()->insert([
            ['id' => $nextReportId, 'goods_id' => $includeGid, 'sku' => $includeSku, 'period' => 'L30', 'ad_status' => 'No ad', 'success' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextReportId + 1, 'goods_id' => $zeroGid, 'sku' => $zeroSku, 'period' => 'L30', 'ad_status' => 'No ad', 'success' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextReportId + 2, 'goods_id' => $activeGid, 'sku' => $activeSku, 'period' => 'L30', 'ad_status' => 'Active', 'success' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextReportId + 3, 'goods_id' => $dupGid, 'sku' => $dupSku, 'period' => 'L30', 'ad_status' => 'No ad', 'success' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $nextReportId + 4, 'goods_id' => $dupGid, 'sku' => $dupSku, 'period' => 'L7', 'ad_status' => 'No ad', 'success' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        if (Schema::hasTable('temu_metrics')) {
            TemuMetric::query()->insert([
                'id' => ((int) TemuMetric::query()->max('id')) + 1,
                'sku' => $metricSku,
                'goods_id' => $metricGid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Temu1MissingAdsController::forgetMissingTotalCache();

        $response = $this->getJson(route('temu.ads.missing.data', [], false));
        $response->assertOk()->assertJsonStructure(['data', 'total']);

        $goodsIds = collect($response->json('data'))->pluck('goods_id')->map(fn ($id) => (string) $id);

        $this->assertTrue($goodsIds->contains($includeGid), 'In-stock No ad goods should be missing');
        $this->assertFalse($goodsIds->contains($zeroGid), 'Zero inventory should not be missing');
        $this->assertFalse($goodsIds->contains($activeGid), 'Active ads should not be missing');
        $this->assertSame(1, $goodsIds->filter(fn ($id) => $id === $dupGid)->count(), 'Duplicate period rows should count once');
        if (Schema::hasTable('temu_metrics')) {
            $this->assertTrue($goodsIds->contains($metricGid), 'Temu 1 goods with no ads report should be missing');
        }

        $includeRow = collect($response->json('data'))->firstWhere('goods_id', $includeGid);
        $this->assertSame('No ad', $includeRow['ad_status'] ?? null);
        $this->assertGreaterThan(0, (int) ($includeRow['inv'] ?? 0));

        $this->assertSame(
            (int) $response->json('total'),
            Temu1MissingAdsController::missingTotalCount()
        );
    }
}
