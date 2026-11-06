<?php

namespace Tests\Unit;

use App\Services\Lqs\WayfairListingQualityScorer;
use PHPUnit\Framework\TestCase;

class WayfairListingQualityScorerTest extends TestCase
{
    private function complete(): array
    {
        return [
            'title' => '5 Core Speaker Stand Adjustable Tripod PA Speakers Holder 35mm Pole Mount',
            'description' => str_repeat('Heavy duty steel stand with a 35mm insert that fits most PA speakers. ', 9),
            'bullets' => array_fill(0, 5, 'Adjustable height from 40 to 72 inches for stages, studios and living rooms alike.'),
            'images' => array_map(fn ($i) => "https://cdn.example.com/img{$i}.jpg", range(1, 8)),
            'live' => true,
            'buyer_link' => 'https://wayfair.com/pdp/x.html',
            'price' => 18.53,
            'stock' => 12,
            'upc' => '810099491524',
            'length' => 36, 'width' => 9, 'height' => 5.5, 'weight' => 10.4,
            'origin' => 'China',
            'has_video' => true,
        ];
    }

    public function test_complete_listing_scores_100(): void
    {
        $result = (new WayfairListingQualityScorer)->score($this->complete());

        $this->assertSame(100.0, $result['score']);
        $this->assertSame('No gaps found by the API checks.', $result['suggestions']);
        $this->assertSame(100, array_sum(array_column($result['checks'], 'max')));
    }

    public function test_empty_listing_scores_low_and_explains_every_gap(): void
    {
        $result = (new WayfairListingQualityScorer)->score(['live' => false]);

        $this->assertLessThan(10, $result['score']);
        foreach (['Title', 'Description', 'Key features', 'Images', 'Listing health', 'Catalog data'] as $name) {
            $this->assertStringContainsString("[{$name}]", $result['suggestions']);
        }
    }

    public function test_description_and_bullets_that_only_repeat_the_title_do_not_count(): void
    {
        $c = $this->complete();
        $c['description'] = $c['title'];
        $c['bullets'] = [$c['title']];

        $facts = (new WayfairListingQualityScorer)->facts($c);

        $this->assertSame(0, $facts['description_chars']);
        $this->assertSame([], $facts['bullets']);
    }

    public function test_title_without_brand_or_with_promo_words_loses_points(): void
    {
        $scorer = new WayfairListingQualityScorer;
        $base = $scorer->score($this->complete())['checks'][0]['points'];

        $noBrand = $this->complete();
        $noBrand['title'] = 'Adjustable Tripod PA Speakers Holder 35mm Pole Mount Stand Black';
        $promo = $this->complete();
        $promo['title'] = '5 Core Speaker Stand Adjustable Tripod Free Shipping Best Price 35mm Mount';

        $this->assertSame(20.0, $base);
        $this->assertSame(17.0, $scorer->score($noBrand)['checks'][0]['points']);
        $this->assertSame(16.0, $scorer->score($promo)['checks'][0]['points']);
    }

    public function test_inactive_out_of_stock_listing_loses_listing_health(): void
    {
        $c = $this->complete();
        $c['live'] = false;
        $c['stock'] = 0;

        $health = (new WayfairListingQualityScorer)->score($c)['checks'][4];

        $this->assertSame('Listing health', $health['name']);
        $this->assertSame(5.0, $health['points']);
    }
}
