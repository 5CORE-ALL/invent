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

    public function test_inventory_push_sets_amount_on_the_sku_id(): void
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

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['updated']);
        Http::assertSent(function ($request) {
            if (($request['method'] ?? '') !== '/icbu/product/inventory/update') {
                return false;
            }
            $payload = json_decode((string) $request['inventory_update_request'], true);
            $item = $payload['inventoryItems'][0] ?? [];

            return str_contains($request->url(), 'https://openapi-api.alibaba.com/rest')
                && ($item['productId'] ?? '') === '160000043250883'
                && ($item['skuId'] ?? '') === '10001889397471'
                && ($item['inventory']['amount'] ?? null) === '77'
                && ! array_key_exists('sku_code', $item);
        });
    }

    public function test_inventory_push_uses_the_only_inventory_sku_when_the_product_has_no_sku_id(): void
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
                            'inventory' => ['amount' => '79'],
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
            if (($request['method'] ?? '') !== '/icbu/product/inventory/update') {
                return false;
            }
            $payload = json_decode((string) $request['inventory_update_request'], true);

            return ($payload['inventoryItems'][0]['skuId'] ?? '') === '58224724203874'
                && ($payload['inventoryItems'][0]['inventory']['amount'] ?? null) === '77';
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
