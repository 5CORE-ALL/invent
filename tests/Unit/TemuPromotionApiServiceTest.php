<?php

namespace Tests\Unit;

use App\Services\TemuApiService;
use App\Services\TemuPromotionApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TemuPromotionApiServiceTest extends TestCase
{
    public function test_enroll_sends_price_in_cents_and_returns_draft_id(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'result' => ['draftId' => 777, 'goodsId' => 11]]),
        ]);

        $res = (new TemuPromotionApiService(app(TemuApiService::class)))
            ->enrollSku(500, 11, 22, 19.99, 3, 'trace');

        $this->assertTrue($res['success']);
        $this->assertSame('777', $res['draft_id']);

        Http::assertSent(function ($request) {
            $d = $request->data();

            return $d['type'] === 'bg.promotion.activity.goods.enroll'
                && $d['activityId'] === 500
                && $d['enrollGoods']['goodsId'] === 11
                && $d['enrollGoods']['activityQuantity'] === 3
                && $d['enrollGoods']['enrollSkuList'][0]['skuId'] === 22
                && $d['enrollGoods']['enrollSkuList'][0]['activitySupplierPrice'] === 1999
                && ! empty($d['sign']);
        });
    }

    public function test_enroll_surfaces_temu_error(): void
    {
        Http::fake([
            '*' => Http::response(['success' => false, 'errorCode' => 220010004, 'errorMsg' => 'The activity has ended']),
        ]);

        $res = (new TemuPromotionApiService(app(TemuApiService::class)))
            ->enrollSku(500, 11, 22, 10.0, 1);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('220010004', $res['message']);
    }

    public function test_enroll_rejects_non_positive_price_without_calling_temu(): void
    {
        Http::fake();

        $res = (new TemuPromotionApiService(app(TemuApiService::class)))
            ->enrollSku(500, 11, 22, 0.0, 1);

        $this->assertFalse($res['success']);
        Http::assertNothingSent();
    }

    public function test_deactivate_sends_operate_type_30_and_returns_draft_id(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'result' => ['draftId' => 888, 'goodsId' => 11]]),
        ]);

        $res = (new TemuPromotionApiService(app(TemuApiService::class)))->deactivateGoods(500, 11, 'trace');

        $this->assertTrue($res['success']);
        $this->assertSame('888', $res['draft_id']);

        Http::assertSent(function ($request) {
            $d = $request->data();

            return $d['type'] === 'bg.promotion.activity.goods.update'
                && $d['activityId'] === 500
                && $d['goodsId'] === 11
                && $d['operateType'] === 30
                && ! isset($d['updateSkuList'], $d['addSkuList'], $d['activityQuantity']);
        });
    }

    public function test_enrolled_goods_reads_prices_in_dollars(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'result' => [
                'total' => 1,
                'goodsList' => [['goodsId' => 11, 'activityQuantity' => 5, 'remainingActivityQuantity' => 4, 'skuList' => [['skuId' => 22, 'activitySupplierPrice' => 1999]]]],
            ]]),
        ]);

        $res = (new TemuPromotionApiService(app(TemuApiService::class)))->enrolledGoods(500);

        $this->assertTrue($res['success']);
        $this->assertSame(19.99, $res['goods']['11']['skus']['22']);
        $this->assertSame(5, $res['goods']['11']['qty']);
    }
}
