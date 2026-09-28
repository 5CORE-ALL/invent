<?php

namespace Tests\Unit;

use App\Services\AlibabaAuthService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AlibabaAuthTokenRequestTest extends TestCase
{
    public function test_create_token_matches_documented_iop_request(): void
    {
        $auth = new AlibabaAuthService();
        $method = new ReflectionMethod($auth, 'signedTokenParams');
        $params = $method->invoke(
            $auth,
            '/auth/token/create',
            ['code' => '0_100132_ABC', 'uuid' => 'do-not-send', 'grant_type' => 'authorization_code'],
            '123456',
            'secret',
            1710000000000
        );

        $this->assertSame('123456', $params['app_key']);
        $this->assertSame('0_100132_ABC', $params['code']);
        $this->assertSame('sha256', $params['sign_method']);
        $this->assertSame('1710000000000', $params['timestamp']);
        $this->assertArrayNotHasKey('uuid', $params);
        $this->assertArrayNotHasKey('grant_type', $params);
        $this->assertArrayNotHasKey('method', $params);

        $source = '/auth/token/createapp_key123456code0_100132_ABCsign_methodsha256timestamp1710000000000';
        $this->assertSame(strtoupper(hash_hmac('sha256', $source, 'secret')), $params['sign']);
    }

    public function test_refresh_token_uses_refresh_endpoint_parameter_only(): void
    {
        $auth = new AlibabaAuthService();
        $method = new ReflectionMethod($auth, 'tokenBusinessParams');

        $this->assertSame(
            ['refresh_token' => 'refresh-1'],
            $method->invoke($auth, '/auth/token/refresh', ['refresh_token' => 'refresh-1', 'code' => 'ignored'])
        );
        $this->assertSame(
            ['code' => '0_code'],
            $method->invoke($auth, '/auth/token/create', ['code' => '0_code', 'uuid' => 'skip'])
        );
    }
}
