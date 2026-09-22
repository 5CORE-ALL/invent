<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\TopDawgInventorySyncService;
use App\Services\TopDawgApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TopDawgMismatchInventoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.topdawg.base_url' => 'https://topdawg.test/supplier/api',
            'services.topdawg.token' => 'test-token-123',
        ]);
    }

    public function test_sku_aliases_cover_hyphen_space_and_compact(): void
    {
        $aliases = TopDawgInventorySyncService::skuAliasesForPush('C10BP 20 10 R');

        $this->assertContains('C10BP 20 10 R', $aliases);
        $this->assertContains('C10BP-20-10-R', $aliases);
        $this->assertContains('C10BP2010R', $aliases);
    }

    public function test_live_qty_prefers_qty_available(): void
    {
        $this->assertSame(255, TopDawgApiService::qtyFromLiveProductRow([
            'quantity' => 0,
            'qty_available' => 255,
        ]));
        $this->assertSame(76, TopDawgApiService::qtyFromLiveProductRow([
            'product' => ['qty_available' => 76],
        ]));
    }

    public function test_inventory_push_sends_product_code_and_qty_available(): void
    {
        $listed = 256;
        Http::fake(function ($request) use (&$listed) {
            if (str_contains($request->url(), '/SupplierProduct/update')) {
                $listed = 255;

                return Http::response([
                    'message' => 'Product submitted successfully for review.',
                    'code' => 200,
                ], 200);
            }
            if (str_contains($request->url(), '/SupplierProduct/list')) {
                return Http::response([
                    'results' => [[
                        'product_code' => 'C10BP 20 10 R',
                        'sku' => 'C10BP 20 10 R',
                        'qty_available' => $listed,
                    ]],
                ], 200);
            }

            return Http::response(['message' => 'not found'], 404);
        });

        $result = (new TopDawgApiService())->updateItemInventory('C10BP 20 10 R', 255);

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/SupplierProduct/update')) {
                return false;
            }
            $body = $request->data();

            return ($body['product_code'] ?? null) === 'C10BP 20 10 R'
                && (int) ($body['qty_available'] ?? -1) === 255;
        });
    }
}
