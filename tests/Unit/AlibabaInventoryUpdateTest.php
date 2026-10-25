<?php

namespace Tests\Unit;

use App\Services\AlibabaApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlibabaInventoryUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.alibaba.app_key' => '123456',
            'services.alibaba.app_secret' => 'test-secret',
            'services.alibaba.access_token' => 'test-token',
        ]);
    }

    public function test_inventory_push_sends_a_plus_delta_for_the_warehouse_on_the_sku_id(): void
    {
        Http::fake(function ($request) {
            $method = (string) ($request['method'] ?? '');
            if ($method === '/icbu/product/get') {
                return Http::response([
                    'product' => [
                        'productSku' => [
                            'skus' => [[
                                'skuCode' => '14B LED',
                                'skuId' => 10001889397471,
                                'inventoryDTOList' => [['inventory' => 10, 'storeCode' => 'US_LOCAL_01']],
                            ]],
                        ],
                    ],
                ]);
            }
            if ($method === '/icbu/product/inventory/update') {
                return Http::response(['success' => true]);
            }

            return Http::response(['message' => 'unexpected '.$method], 400);
        });

        $result = app(AlibabaApiService::class)->batchUpdateInventory([[
            'product_id' => '160000043250883',
            'sku_code' => '14B LED',
            'inventory' => 77,
            'shopify_qty' => 77,
        ]]);

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertSame(1, $result['updated']);
        Http::assertSent(function ($request) {
            if (($request['method'] ?? '') !== '/icbu/product/inventory/update') {
                return false;
            }
            $list = json_decode((string) $request['inventory_list'], true);
            $row = $list[0] ?? [];

            return str_contains($request->url(), 'https://openapi-api.alibaba.com/rest')
                && ($request['product_id'] ?? '') === '160000043250883'
                && ! isset($request['inventory_update_request'])
                && ($row['sku_id'] ?? null) === 10001889397471
                && ($row['inventory_code'] ?? '') === 'US_LOCAL_01'
                && ($row['inventory'] ?? null) === 67
                && ($row['operate'] ?? '') === 'plus';
        });
    }

    public function test_inventory_push_subtracts_when_alibaba_holds_more_and_skips_when_equal(): void
    {
        Http::fake(function ($request) {
            $method = (string) ($request['method'] ?? '');
            if ($method === '/icbu/product/get') {
                return Http::response([
                    'product' => [
                        'productSku' => [
                            'skus' => [[
                                'skuCode' => '14B LED',
                                'skuId' => 10001889397471,
                                'inventoryDTOList' => [['inventory' => 90, 'storeCode' => 'US_LOCAL_01']],
                            ]],
                        ],
                    ],
                ]);
            }
            if ($method === '/icbu/product/inventory/update') {
                return Http::response(['success' => true]);
            }

            return Http::response(['message' => 'unexpected '.$method], 400);
        });

        $result = app(AlibabaApiService::class)->batchUpdateInventory([[
            'product_id' => '160000043250883',
            'sku_code' => '14B LED',
            'inventory' => 77,
            'shopify_qty' => 77,
        ]]);

        $this->assertTrue($result['success'], $result['message'] ?? '');
        Http::assertSent(function ($request) {
            $list = json_decode((string) ($request['inventory_list'] ?? '[]'), true);

            return ($request['method'] ?? '') === '/icbu/product/inventory/update'
                && ($list[0]['inventory'] ?? null) === 13
                && ($list[0]['operate'] ?? '') === 'sub';
        });
    }

    public function test_inventory_push_uses_the_inventory_lookup_when_the_product_has_no_sku_id(): void
    {
        Http::fake(function ($request) {
            $method = (string) ($request['method'] ?? '');
            if ($method === '/icbu/product/get') {
                return Http::response(['product' => ['subject' => 'Speaker']]);
            }
            if ($method === '/icbu/product/inventory/get') {
                return Http::response([
                    'result' => [
                        'inventoryItems' => [[
                            'skuId' => '58224724203874',
                            'inventoryCode' => 'US_LOCAL_01',
                            'inventory' => 79,
                        ]],
                    ],
                ]);
            }
            if ($method === '/icbu/product/inventory/update') {
                return Http::response(['success' => true]);
            }

            return Http::response(['message' => 'unexpected '.$method], 400);
        });

        $result = app(AlibabaApiService::class)->batchUpdateInventory([[
            'product_id' => '160000043250883',
            'sku_code' => '14B LED',
            'inventory' => 77,
            'shopify_qty' => 77,
        ]]);

        $this->assertTrue($result['success'], $result['message'] ?? '');
        Http::assertSent(function ($request) {
            if (($request['method'] ?? '') === '/icbu/product/inventory/get') {
                return ($request['product_id'] ?? '') === '160000043250883'
                    && ($request['language'] ?? '') === 'ENGLISH';
            }
            if (($request['method'] ?? '') !== '/icbu/product/inventory/update') {
                return false;
            }
            $list = json_decode((string) $request['inventory_list'], true);

            return ($list[0]['sku_id'] ?? null) === 58224724203874
                && ($list[0]['inventory'] ?? null) === 2
                && ($list[0]['operate'] ?? '') === 'sub';
        });
    }

    public function test_inventory_push_starts_stock_on_a_listing_with_no_stock_rows(): void
    {
        Http::fake(function ($request) {
            $method = (string) ($request['method'] ?? '');
            if ($method === '/icbu/product/get') {
                return Http::response(['product' => ['subject' => 'Camera Screw']]);
            }
            if ($method === '/icbu/product/inventory/get') {
                return Http::response(['result' => ['data_list' => [], 'success' => true], 'code' => '0']);
            }
            if ($method === '/icbu/product/inventory/update') {
                return Http::response(['result' => ['data' => true, 'success' => true], 'code' => '0']);
            }

            return Http::response(['message' => 'unexpected '.$method], 400);
        });

        $result = app(AlibabaApiService::class)->batchUpdateInventory([[
            'product_id' => '10000043407354',
            'sku_code' => '1/4" 3/8" Camera Screw 5Pcs',
            'inventory' => 145,
            'shopify_qty' => 145,
        ]]);

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertSame(1, $result['updated']);
        Http::assertSent(function ($request) {
            if (($request['method'] ?? '') !== '/icbu/product/inventory/update') {
                return false;
            }
            $list = json_decode((string) $request['inventory_list'], true);

            return ($list[0]['sku_id'] ?? null) === -1
                && ($list[0]['inventory'] ?? null) === 145
                && ($list[0]['operate'] ?? '') === 'plus'
                && ($list[0]['inventory_code'] ?? '') === 'US_LOCAL_01';
        });
    }

    public function test_inventory_push_returns_the_alibaba_error(): void
    {
        Http::fake(function ($request) {
            $method = (string) ($request['method'] ?? '');
            if ($method === '/icbu/product/get') {
                return Http::response([
                    'product' => [
                        'productSku' => [
                            'skus' => [[
                                'skuCode' => '14B LED',
                                'skuId' => 10001889397471,
                                'inventoryDTOList' => [['inventory' => 10, 'storeCode' => 'US_LOCAL_01']],
                            ]],
                        ],
                    ],
                ]);
            }

            return Http::response([
                'success' => false,
                'message' => 'skuId is invalid',
            ]);
        });

        $result = app(AlibabaApiService::class)->batchUpdateInventory([[
            'product_id' => '160000043250883',
            'sku_code' => '14B LED',
            'inventory' => 77,
            'shopify_qty' => 77,
        ]]);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['updated']);
        $this->assertStringContainsString('skuId is invalid', $result['message']);
        $this->assertSame(['14B LED'], $result['failed_skus']);
    }
}
