<?php

namespace Tests\Unit;

use App\Services\DobaApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DobaInventoryUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $conf = 'C:/xampp/php/extras/ssl/openssl.cnf';
        if (is_file($conf)) {
            putenv('OPENSSL_CONF='.$conf);
        }

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => is_file($conf) ? $conf : null,
        ]);
        $this->assertNotFalse($key);
        $exported = openssl_pkey_export($key, $pem, null, is_file($conf) ? ['config' => $conf] : []);
        $this->assertTrue($exported);
        $raw = trim(str_replace(
            ['-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----', '-----BEGIN RSA PRIVATE KEY-----', '-----END RSA PRIVATE KEY-----', "\r", "\n"],
            '',
            $pem
        ));

        config([
            'services.doba.app_key' => 'test-app-key',
            'services.doba.private_key' => $raw,
        ]);
    }

    public function test_inventory_push_uses_the_stock_update_endpoint(): void
    {
        Http::fake([
            'https://openapi.doba.com/api/goods/stock/update' => Http::response([
                'responseCode' => '000000',
                'responseMessage' => 'Success',
                'businessData' => ['businessStatus' => '000000', 'successful' => true],
            ]),
        ]);

        $result = app(DobaApiService::class)->updateItemInventory('GS EL HYBRID', 1128);

        $this->assertTrue($result['success']);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://openapi.doba.com/api/goods/stock/update'
                && $request['itemNo'] === 'GS EL HYBRID'
                && $request['inventory'] === 1128;
        });
    }

    public function test_inventory_push_returns_the_doba_error(): void
    {
        Http::fake([
            'https://openapi.doba.com/api/goods/stock/update' => Http::response([
                'responseCode' => '610001',
                'responseMessage' => 'itemNo does not exist',
            ]),
        ]);

        $result = app(DobaApiService::class)->updateItemInventory('GS EL HYBRID', 0);

        $this->assertFalse($result['success']);
        $this->assertSame('itemNo does not exist', $result['message']);
    }
}
