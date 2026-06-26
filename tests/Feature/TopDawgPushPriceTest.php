<?php

namespace Tests\Feature;

use App\Services\TopDawgApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for the price-push side of TopDawgApiService.
 *
 * These tests use Http::fake(), so NO real TopDawg API call is made. They
 * lock in the *contract* the production code can rely on:
 *
 *   - URL is built from the configured base_url + the chosen endpoint
 *   - Bearer-token authorization header is sent
 *   - Body shape matches the requested variant (`flat`, `flat_tdid`,
 *     `items_array`, `products`, `data`, `id_price`)
 *   - HTTP method (POST / PUT / PATCH) is honoured
 *   - The returned array reports `ok`, `status`, `url`, `request`, `response`
 *
 * Run:
 *   php artisan test tests/Feature/TopDawgPushPriceTest.php
 *
 * Run single test:
 *   php artisan test --filter test_push_price_sends_bearer_token_to_configured_endpoint
 *
 * To discover the *real* endpoint against the live TopDawg API, use the
 * exploratory command instead (it intentionally lives outside PHPUnit):
 *   php artisan topdawg:test-push-price --sku=YOUR-TEST-SKU --price=19.99
 */
class TopDawgPushPriceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Stable config for every test in this file — keeps assertions deterministic
        // even if .env (or a global service binding) sets different values.
        config([
            'services.topdawg.base_url' => 'https://topdawg.test/supplier/api',
            'services.topdawg.token'    => 'test-token-123',
        ]);
    }

    /**
     * Convenience: returns a fresh service instance picking up the per-test
     * config above (a singleton bound earlier in the boot would cache the old
     * token, which is why we instantiate directly rather than `app(...)`).
     */
    private function service(): TopDawgApiService
    {
        return new TopDawgApiService();
    }

    // -----------------------------------------------------------------------
    // Body builder — pure, no HTTP. Verifies every supported shape.
    // -----------------------------------------------------------------------

    public function test_build_push_price_body_pc_sku_is_the_confirmed_default(): void
    {
        // pc_sku is the body shape that TopDawg's POST /SupplierProduct/update
        // accepts (confirmed via topdawg:test-push-price probe — returns
        // 200 with "Product submitted successfully for review."). Locking it in
        // here protects future edits from silently changing the request shape.
        $svc = $this->service();
        $this->assertSame(
            ['product_code' => 'ABC', 'price' => 19.99],
            $svc->buildPushPriceBody('ABC', 19.99, 'TDID-1', 'pc_sku'),
        );
    }

    public function test_build_push_price_body_pc_tdid_uses_tdid_falling_back_to_sku(): void
    {
        $svc = $this->service();
        $this->assertSame(
            ['product_code' => 'TDID-1', 'price' => 19.99],
            $svc->buildPushPriceBody('ABC', 19.99, 'TDID-1', 'pc_tdid'),
        );
        $this->assertSame(
            ['product_code' => 'ABC', 'price' => 19.99],
            $svc->buildPushPriceBody('ABC', 19.99, null, 'pc_tdid'),
        );
    }

    public function test_build_push_price_body_pc_array_wraps_in_products(): void
    {
        $svc = $this->service();
        $this->assertSame(
            ['products' => [['product_code' => 'ABC', 'price' => 5.0]]],
            $svc->buildPushPriceBody('ABC', 5.0, 'TDID-1', 'pc_array_sku'),
        );
        $this->assertSame(
            ['products' => [['product_code' => 'TDID-1', 'price' => 5.0]]],
            $svc->buildPushPriceBody('ABC', 5.0, 'TDID-1', 'pc_array_tdid'),
        );
    }

    public function test_build_push_price_body_flat_shape_legacy(): void
    {
        // Kept around so the probe command can still try this against sibling
        // endpoints — TopDawg's /SupplierProduct/update specifically requires
        // `product_code`, but other potential routes might key differently.
        $svc = $this->service();
        $this->assertSame(
            ['sku' => 'ABC', 'price' => 19.99],
            $svc->buildPushPriceBody('ABC', 19.99, 'TDID-1', 'flat'),
        );
    }

    public function test_build_push_price_body_flat_tdid_shape(): void
    {
        $svc = $this->service();
        $this->assertSame(
            ['tdid' => 'TDID-1', 'price' => 9.99],
            $svc->buildPushPriceBody('ABC', 9.99, 'TDID-1', 'flat_tdid'),
        );
    }

    public function test_build_push_price_body_flat_tdid_falls_back_to_sku_when_tdid_missing(): void
    {
        $svc = $this->service();
        // No TDID → builder must use the SKU as a stand-in so the request still
        // carries the field the endpoint expects rather than silently sending null.
        $this->assertSame(
            ['tdid' => 'ABC', 'price' => 9.99],
            $svc->buildPushPriceBody('ABC', 9.99, null, 'flat_tdid'),
        );
    }

    public function test_build_push_price_body_items_array_shape(): void
    {
        $svc = $this->service();
        $this->assertSame(
            ['items' => [['sku' => 'ABC', 'price' => 5.0]]],
            $svc->buildPushPriceBody('ABC', 5.0, null, 'items_array'),
        );
    }

    public function test_build_push_price_body_products_shape(): void
    {
        $svc = $this->service();
        $this->assertSame(
            ['products' => [['sku' => 'ABC', 'price' => 5.0]]],
            $svc->buildPushPriceBody('ABC', 5.0, null, 'products'),
        );
    }

    public function test_build_push_price_body_data_shape(): void
    {
        $svc = $this->service();
        $this->assertSame(
            ['data' => [['sku' => 'ABC', 'price' => 5.0]]],
            $svc->buildPushPriceBody('ABC', 5.0, null, 'data'),
        );
    }

    public function test_build_push_price_body_id_price_shape_prefers_tdid(): void
    {
        $svc = $this->service();
        $this->assertSame(
            ['id' => 'TDID-1', 'price' => 5.0],
            $svc->buildPushPriceBody('ABC', 5.0, 'TDID-1', 'id_price'),
        );
    }

    public function test_build_push_price_body_unknown_shape_falls_back_to_flat(): void
    {
        $svc = $this->service();
        // Defensive default — if a future code path passes a typo'd shape we still
        // produce a sane request instead of throwing.
        $this->assertSame(
            ['sku' => 'ABC', 'price' => 5.0],
            $svc->buildPushPriceBody('ABC', 5.0, 'TDID-1', 'totally-unknown-shape'),
        );
    }

    // -----------------------------------------------------------------------
    // HTTP behavior — verified with Http::fake().
    // -----------------------------------------------------------------------

    public function test_push_price_default_call_uses_confirmed_topdawg_contract(): void
    {
        // Pins the EXACT contract confirmed against the live TopDawg API:
        //   POST /SupplierProduct/update
        //   Body: { product_code: <sku>, price: <float> }
        // Any future change that breaks this should fail loudly here.
        Http::fake([
            '*' => Http::response(
                ['message' => 'Product submitted successfully for review.', 'code' => 200],
                200,
            ),
        ]);

        $r = $this->service()->pushPrice('ABC-123', 19.99);

        $this->assertTrue($r['ok']);
        $this->assertSame(200, $r['status']);
        $this->assertSame('https://topdawg.test/supplier/api/SupplierProduct/update', $r['url']);
        // Response surfaces TopDawg's "submitted for review" payload so callers
        // can show it as a status (the price isn't live until TD approves).
        $this->assertSame(
            ['message' => 'Product submitted successfully for review.', 'code' => 200],
            $r['response'],
        );

        Http::assertSent(function ($req) {
            return $req->method() === 'POST'
                && $req->url() === 'https://topdawg.test/supplier/api/SupplierProduct/update'
                && $req->hasHeader('Authorization', 'Bearer test-token-123')
                && $req->hasHeader('Content-Type', 'application/json')
                && $req->data() === ['product_code' => 'ABC-123', 'price' => 19.99];
        });
    }

    public function test_push_price_uses_overridden_endpoint_and_shape_and_method(): void
    {
        Http::fake([
            '*' => Http::response(['updated' => true], 200),
        ]);

        $r = $this->service()->pushPrice(
            sku:       'ABC-123',
            price:     12.50,
            tdid:      'TDID-555',
            endpoint:  '/SupplierProduct/updatePrice',
            bodyShape: 'items_array',
            method:    'PUT',
        );

        $this->assertTrue($r['ok']);
        $this->assertSame('https://topdawg.test/supplier/api/SupplierProduct/updatePrice', $r['url']);
        $this->assertSame(['items' => [['sku' => 'ABC-123', 'price' => 12.50]]], $r['request']);

        Http::assertSent(function ($req) {
            return $req->method() === 'PUT'
                && $req->url() === 'https://topdawg.test/supplier/api/SupplierProduct/updatePrice'
                && $req->data() === ['items' => [['sku' => 'ABC-123', 'price' => 12.50]]];
        });
    }

    public function test_push_price_returns_ok_false_on_non_2xx(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'not found'], 404),
        ]);

        $r = $this->service()->pushPrice('ABC', 9.99);

        $this->assertFalse($r['ok']);
        $this->assertSame(404, $r['status']);
        $this->assertSame(['error' => 'not found'], $r['response']);
    }

    public function test_push_price_keeps_raw_body_when_response_is_not_json(): void
    {
        // Some endpoints return plain text on error — make sure we don't lose
        // that diagnostic info by coercing through json_decode → null.
        Http::fake([
            '*' => Http::response('Internal Server Error', 500),
        ]);

        $r = $this->service()->pushPrice('ABC', 9.99);

        $this->assertFalse($r['ok']);
        $this->assertSame(500, $r['status']);
        $this->assertSame('Internal Server Error', $r['response']);
    }

    public function test_push_price_throws_when_token_missing(): void
    {
        // Reset both the cached binding and the config so the constructor sees
        // a null token. (config repository is mutable in the test runtime.)
        config(['services.topdawg.token' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/TOPDAWG_API_TOKEN/');

        (new TopDawgApiService())->pushPrice('ABC', 9.99);
    }
}
