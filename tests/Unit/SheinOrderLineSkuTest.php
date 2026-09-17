<?php

namespace Tests\Unit;

use App\Services\SheinApiService;
use PHPUnit\Framework\TestCase;

class SheinOrderLineSkuTest extends TestCase
{
    public function test_listing_sku_wins_over_wrong_order_seller_sku(): void
    {
        $svc = $this->resolver([
            'I7mlezsxjcta22' => 'SS HD 1PK 5FT',
        ]);

        $this->assertSame('SS HD 1PK 5FT', $svc->resolveOrderLineSku([
            'sellerSku' => 'HISE 4X10',
            'goodsSn' => 'HISE 4X10',
            'skuCode' => 'I7mlezsxjcta22',
        ]));
    }

    public function test_usable_seller_sku_used_when_listing_is_unknown(): void
    {
        $svc = $this->resolver([]);

        $this->assertSame('HISE 4X10', $svc->resolveOrderLineSku([
            'sellerSku' => 'HISE 4X10',
            'skuCode' => 'Iunknownsku999',
        ]));
    }

    public function test_hash_seller_sku_falls_back_to_listing(): void
    {
        $svc = $this->resolver([
            'I7mlezsxjcta22' => 'SS HD 1PK 5FT',
        ]);

        $this->assertSame('SS HD 1PK 5FT', $svc->resolveOrderLineSku([
            'sellerSku' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90',
            'skuCode' => 'I7mlezsxjcta22',
        ]));
    }

    /**
     * @param  array<string, string>  $map
     */
    private function resolver(array $map): SheinApiService
    {
        return new class($map) extends SheinApiService
        {
            /** @param array<string, string> $map */
            public function __construct(private array $map)
            {
            }

            public function sellerSkuForSheinSkuCode(string $code): string
            {
                return $this->map[trim($code)] ?? '';
            }
        };
    }
}
