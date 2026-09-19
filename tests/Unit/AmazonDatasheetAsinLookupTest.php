<?php

namespace Tests\Unit;

use App\Models\AmazonDatasheet;
use PHPUnit\Framework\TestCase;

class AmazonDatasheetAsinLookupTest extends TestCase
{
    public function test_asins_by_product_skus_match_case_and_space_variants(): void
    {
        $sheet = new AmazonDatasheet([
            'sku' => 'GS EL POWER',
            'asin' => 'B0H99D533F',
            'price' => 6.75,
        ]);
        $sheet->id = 1351;

        $map = AmazonDatasheet::asinsByProductSkus(
            ['GS EL Power', 'GSELPower', 'unknown-sku'],
            [$sheet]
        );

        $this->assertSame('B0H99D533F', $map['GS EL Power']);
        $this->assertSame('B0H99D533F', $map['GSELPower']);
        $this->assertArrayNotHasKey('unknown-sku', $map);
    }

    public function test_asins_by_product_skus_prefer_space_normalized_msku(): void
    {
        $compact = new AmazonDatasheet([
            'sku' => 'GSELPOWER',
            'asin' => 'B0AAAAAAA1',
            'price' => 11.00,
        ]);
        $compact->id = 1;
        $exact = new AmazonDatasheet([
            'sku' => 'GS EL POWER',
            'asin' => 'B0H99D533F',
            'price' => 6.75,
        ]);
        $exact->id = 1351;

        $map = AmazonDatasheet::asinsByProductSkus(['GS EL Power'], [$compact, $exact]);

        $this->assertSame('B0H99D533F', $map['GS EL Power']);
    }
}
