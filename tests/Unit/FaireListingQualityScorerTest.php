<?php

namespace Tests\Unit;

use App\Services\Lqs\FaireListingQualityScorer;
use PHPUnit\Framework\TestCase;

class FaireListingQualityScorerTest extends TestCase
{
    private function complete(): array
    {
        return [
            'title' => '5 Core Speaker Stand Adjustable Tripod PA Speakers Holder 35mm Pole Mount',
            'description' => str_repeat('Heavy duty steel stand with a 35mm insert that fits most PA speakers. ', 10),
            'images' => array_map(fn ($i) => ['url' => "https://cdn.example.com/img{$i}.jpg", 'width' => 1500, 'height' => 1500], range(1, 7)),
            'has_taxonomy' => true,
            'attribute_count' => 3,
            'warnings' => [],
            'wholesale' => 9.76,
            'retail' => 39.0,
            'lifecycle' => 'PUBLISHED',
            'sale_state' => 'FOR_SALE',
            'stock' => 40,
            'gtin' => '810199690179',
            'length' => 31, 'width' => 5, 'height' => 5, 'weight' => 5.4,
            'tariff' => '851822',
        ];
    }

    public function test_complete_listing_scores_100(): void
    {
        $result = (new FaireListingQualityScorer)->score($this->complete());

        $this->assertSame(100.0, $result['score']);
        $this->assertSame('No gaps found by the API checks.', $result['suggestions']);
        $this->assertSame(100, array_sum(array_column($result['checks'], 'max')));
    }

    public function test_empty_listing_scores_low_and_explains_every_gap(): void
    {
        $result = (new FaireListingQualityScorer)->score([]);

        $this->assertLessThan(10, $result['score']);
        $this->assertStringContainsString('Product name is missing', $result['suggestions']);
        $this->assertStringContainsString('not published', $result['suggestions']);
    }

    public function test_out_of_stock_and_low_markup_lose_points(): void
    {
        $data = $this->complete();
        $data['stock'] = 0;
        $data['retail'] = 15.0; // 1.5x wholesale

        // -6 stock, -2 markup
        $this->assertSame(92.0, (new FaireListingQualityScorer)->score($data)['score']);
    }

    public function test_unpublished_variant_is_penalised(): void
    {
        $data = $this->complete();
        $data['lifecycle'] = 'DRAFT';

        $this->assertSame(95.0, (new FaireListingQualityScorer)->score($data)['score']);
    }
}
