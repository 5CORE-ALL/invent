<?php

namespace Tests\Unit;

use App\Services\Lqs\B5cb2bListingQualityScorer;
use App\Services\Lqs\NeweggListingQualityScorer;
use App\Services\Lqs\PlsListingQualityScorer;
use PHPUnit\Framework\TestCase;

class StoredListingScorersTest extends TestCase
{
    private const TITLE = '5 Core Guitar Stand Adjustable Double Guitars Stands Floor w Neck Holder for Acoustic Electric Bass';

    private function images(int $n): array
    {
        return array_map(fn ($i) => "https://cdn.example.com/img{$i}.jpg", range(1, $n));
    }

    public function test_complete_newegg_listing_scores_100(): void
    {
        $r = (new NeweggListingQualityScorer)->score([
            'title' => self::TITLE,
            'bullets' => implode("\n", array_fill(0, 5, 'HOLDS 2 GUITARS - Keep two guitars organized on one double guitar stand, designed for acoustic, electric and bass guitars.')),
            'images' => implode(',', $this->images(7)),
            'price' => 15.13, 'msrp' => 29.99, 'stock' => 371, 'status' => 'Active', 'active' => true,
            'upc' => '810199694085', 'length' => 17.6, 'width' => 11.5, 'height' => 5.4, 'weight' => 3.6,
            'category' => 'MI - Acoustic-Electric Guitars',
        ]);

        $this->assertSame(100.0, $r['score']);
        $this->assertSame(100, array_sum(array_column($r['checks'], 'max')));
        $this->assertSame('No gaps found by the API checks.', $r['suggestions']);
    }

    public function test_newegg_inactive_without_msrp_loses_points(): void
    {
        $r = (new NeweggListingQualityScorer)->score([
            'title' => self::TITLE,
            'bullets' => implode("\n", array_fill(0, 5, 'HOLDS 2 GUITARS - Keep two guitars organized on one double guitar stand, designed for acoustic, electric and bass guitars.')),
            'images' => $this->images(7),
            'price' => 15.13, 'msrp' => 0, 'stock' => 371, 'status' => 'Inactive', 'active' => true,
            'upc' => '810199694085', 'length' => 17.6, 'width' => 11.5, 'height' => 5.4, 'weight' => 3.6, 'category' => 'x',
        ]);

        // -5 (no MSRP) -6 (inactive)
        $this->assertSame(89.0, $r['score']);
    }

    public function test_complete_pls_listing_scores_100(): void
    {
        $r = (new PlsListingQualityScorer)->score([
            'title' => self::TITLE,
            'description' => '<p>'.str_repeat('Heavy duty steel stand that holds two guitars safely. ', 14).'</p>',
            'images' => json_encode($this->images(8)),
            'price' => 29.99, 'stock' => 12, 'status' => 'active', 'vendor' => '5 Core', 'product_type' => 'Guitar Stands',
        ]);

        $this->assertSame(100.0, $r['score']);
        $this->assertSame(100, array_sum(array_column($r['checks'], 'max')));
    }

    public function test_draft_out_of_stock_pls_listing_loses_listing_health(): void
    {
        $r = (new PlsListingQualityScorer)->score([
            'title' => self::TITLE,
            'description' => str_repeat('Heavy duty steel stand that holds two guitars safely. ', 14),
            'images' => $this->images(8),
            'price' => 29.99, 'stock' => 0, 'status' => 'draft', 'vendor' => '5 Core', 'product_type' => 'Guitar Stands',
        ]);

        $this->assertSame(85.0, $r['score']);
        $this->assertStringContainsString('not active', $r['suggestions']);
    }

    public function test_complete_b5c_listing_scores_100(): void
    {
        $r = (new B5cb2bListingQualityScorer)->score([
            'title' => self::TITLE,
            'short_description' => str_repeat('Adjustable double guitar stand for acoustic and electric guitars. ', 3),
            'images' => $this->images(9),
            'category_count' => 1, 'has_brand' => true,
            'price' => 32.99, 'selling_price' => 14.85, 'stock' => 300, 'in_stock' => true, 'active' => true,
            'length' => 17, 'width' => 11, 'height' => 5, 'weight' => 4,
        ]);

        $this->assertSame(100.0, $r['score']);
        $this->assertSame(100, array_sum(array_column($r['checks'], 'max')));
    }

    public function test_empty_listings_score_low_for_every_channel(): void
    {
        foreach ([new NeweggListingQualityScorer, new PlsListingQualityScorer, new B5cb2bListingQualityScorer] as $scorer) {
            $r = $scorer->score([]);
            $this->assertLessThan(5, $r['score'], get_class($scorer));
            $this->assertStringContainsString('is missing', $r['suggestions']);
        }
    }
}
