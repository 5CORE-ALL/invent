<?php

namespace Tests\Unit;

use App\Services\MarketplaceManager\Temu2OrderLineSkuResolver;
use PHPUnit\Framework\TestCase;

class Temu2OrderLineSkuTest extends TestCase
{
    public function test_unique_listing_sku_wins_over_leftover_ext_code(): void
    {
        $svc = $this->resolver([
            '35638162245702' => ['GRack 5N1 OVAL'],
        ]);

        $this->assertSame('GRack 5N1 OVAL', $svc->resolve(
            '35638162245702',
            'GRack 3N1 OVAL'
        ));
    }

    public function test_aliased_sku_id_keeps_order_ext_code(): void
    {
        $svc = $this->resolver([
            '43543049574947' => ['CS 04 2W 4PCS', 'CS 04 2W 2PAIR'],
        ]);

        $this->assertSame('CS 04 2W 2PAIR', $svc->resolve(
            '43543049574947',
            'CS 04 2W 2PAIR'
        ));
    }

    public function test_missing_listing_keeps_ext_code(): void
    {
        $svc = $this->resolver([]);

        $this->assertSame('GRack 3N1 OVAL', $svc->resolve(
            '999',
            'GRack 3N1 OVAL'
        ));
    }

    /**
     * @param  array<string, list<string>>  $map
     */
    private function resolver(array $map): Temu2OrderLineSkuResolver
    {
        return new class($map) extends Temu2OrderLineSkuResolver
        {
            /** @param array<string, list<string>> $map */
            public function __construct(private array $map)
            {
            }

            protected function lookupListingSku(string $skuId): string
            {
                $skus = collect($this->map[$skuId] ?? [])
                    ->map(static fn ($sku) => trim((string) $sku))
                    ->filter(static fn (string $sku) => self::isUsableSku($sku))
                    ->unique(static fn (string $sku) => mb_strtolower($sku))
                    ->values();

                return $skus->count() === 1 ? (string) $skus->first() : '';
            }
        };
    }
}
