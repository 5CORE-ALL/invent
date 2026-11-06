<?php

namespace Tests\Unit;

use App\Services\Lqs\TopdawgListingQualityScorer;
use PHPUnit\Framework\TestCase;

class TopdawgListingQualityScorerTest extends TestCase
{
    private function complete(): array
    {
        return [
            'title' => '5 Core Speaker Stand Adjustable Tripod PA Speakers Holder 35mm Pole Mount Heavy Duty',
            'description' => str_repeat('Heavy duty steel stand with a 35mm insert that fits most PA speakers. ', 10),
            'bullets' => array_fill(0, 5, 'Adjustable height from 40 to 72 inches for stages, studios and living rooms alike.'),
            'images' => array_map(fn ($i) => "https://cdn.example.com/img{$i}.jpg", range(1, 8)),
            'price' => 18.53,
            'msrp' => 29.99,
            'stock' => 12,
            'published' => true,
            'has_live_id' => true,
            'upc' => '810099491524',
            'length' => 36, 'width' => 9, 'height' => 5.5, 'weight' => 10.4,
            'origin' => 'China',
        ];
    }

    public function test_complete_listing_scores_100(): void
    {
        $result = (new TopdawgListingQualityScorer)->score($this->complete());

        $this->assertSame(100.0, $result['score']);
        $this->assertSame('No gaps found by the API checks.', $result['suggestions']);
        $this->assertSame(100, array_sum(array_column($result['checks'], 'max')));
    }

    public function test_empty_listing_scores_low_and_explains_every_gap(): void
    {
        $result = (new TopdawgListingQualityScorer)->score([]);

        $this->assertLessThan(10, $result['score']);
        $this->assertStringContainsString('Title is missing', $result['suggestions']);
        $this->assertStringContainsString('not published', $result['suggestions']);
    }

    public function test_out_of_stock_and_msrp_below_price_lose_points(): void
    {
        $data = $this->complete();
        $data['stock'] = 0;
        $data['msrp'] = 10;
        $result = (new TopdawgListingQualityScorer)->score($data);

        // -6 stock, -4 MSRP below price
        $this->assertSame(90.0, $result['score']);
    }

    public function test_promo_title_is_penalised(): void
    {
        $data = $this->complete();
        $data['title'] = '5 Core Speaker Stand Free Shipping Best Price Adjustable Tripod PA Speakers Holder';

        $this->assertSame(95.0, (new TopdawgListingQualityScorer)->score($data)['score']);
    }
}
