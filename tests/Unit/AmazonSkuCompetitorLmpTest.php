<?php

namespace Tests\Unit;

use App\Models\AmazonSkuCompetitor;
use PHPUnit\Framework\TestCase;

class AmazonSkuCompetitorLmpTest extends TestCase
{
    public function test_landed_price_adds_paid_delivery_only(): void
    {
        $free = (object) [
            'price' => 29.99,
            'delivery' => ['FREE delivery Thursday, September 3 on orders shipped by Amazon over $35'],
        ];
        $paid = (object) [
            'price' => 38.99,
            'delivery' => ['$49.99 delivery Tuesday, September 8. Details'],
        ];

        $this->assertSame(29.99, AmazonSkuCompetitor::landedPrice($free));
        $this->assertSame(88.98, AmazonSkuCompetitor::landedPrice($paid));
    }

    public function test_sibling_ignore_applies_to_same_asin(): void
    {
        $pink = (object) ['id' => 6, 'asin' => 'B0CKPMCDWW', 'price' => 29.99, 'ignored' => true];
        $yellow = (object) ['id' => 99, 'asin' => 'B0CKPMCDWW', 'price' => 29.99, 'ignored' => false];
        $other = (object) ['id' => 7, 'asin' => 'B0DZCTKGSN', 'price' => 75.99, 'ignored' => false];

        $rows = AmazonSkuCompetitor::applyIgnoreToSameAsins([$pink, $yellow, $other]);

        $this->assertTrue(AmazonSkuCompetitor::isIgnored($rows[0]));
        $this->assertTrue(AmazonSkuCompetitor::isIgnored($rows[1]));
        $this->assertFalse(AmazonSkuCompetitor::isIgnored($rows[2]));
    }

    public function test_l1_skips_ignored_sibling_and_uses_landed_price(): void
    {
        $rows = [
            (object) [
                'id' => 6,
                'asin' => 'B0CKPMCDWW',
                'price' => 29.99,
                'ignored' => true,
                'delivery' => ['FREE delivery'],
            ],
            (object) [
                'id' => 12,
                'asin' => 'B0CY1PGLRX',
                'price' => 38.99,
                'ignored' => false,
                'delivery' => ['$49.99 delivery Tuesday, September 8. Details'],
            ],
            (object) [
                'id' => 45,
                'asin' => 'B0DZCTKGSN',
                'price' => 75.99,
                'ignored' => false,
                'delivery' => ['FREE delivery'],
            ],
        ];

        $rows = AmazonSkuCompetitor::applyIgnoreToSameAsins($rows);
        $lowest = AmazonSkuCompetitor::lowestFromCollection($rows);

        $this->assertNotNull($lowest);
        $this->assertSame('B0DZCTKGSN', $lowest->asin);
        $this->assertSame(75.99, AmazonSkuCompetitor::landedPrice($lowest));
    }

    public function test_dedupe_keeps_lowest_landed_copy_of_asin(): void
    {
        $stale = (object) ['id' => 1, 'asin' => 'B0CKPMCDWW', 'price' => 80.00, 'ignored' => false];
        $cheap = (object) ['id' => 2, 'asin' => 'B0CKPMCDWW', 'price' => 29.99, 'ignored' => false];

        $unique = AmazonSkuCompetitor::dedupeByAsin([$stale, $cheap]);

        $this->assertCount(1, $unique);
        $this->assertSame(2, $unique->first()->id);
        $this->assertSame(29.99, (float) $unique->first()->price);
    }
}
