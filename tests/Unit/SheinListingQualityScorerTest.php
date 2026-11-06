<?php

namespace Tests\Unit;

use App\Services\Lqs\SheinListingQualityScorer;
use PHPUnit\Framework\TestCase;

class SheinListingQualityScorerTest extends TestCase
{
    private function complete(): array
    {
        return [
            'title' => '5 Core Speaker Stand Adjustable Tripod PA Holder 35mm Mount',
            'description' => str_repeat('Heavy duty steel stand with a 35mm insert that fits most PA speakers. ', 5),
            'bullets' => array_fill(0, 5, 'Adjustable height from 40 to 72 inches for stages, studios and living rooms.'),
            'images' => array_map(fn ($i) => "https://img.example.com/{$i}.jpg", range(1, 8)),
            'attributes' => 8,
            'active' => true,
            'listed' => true,
            'price' => 9.68,
            'stock' => 10,
            'length' => 22.8, 'width' => 10.1, 'height' => 7.6, 'weight' => 386,
            'has_brand' => true,
            'has_video' => true,
        ];
    }

    public function test_complete_listing_scores_100(): void
    {
        $result = (new SheinListingQualityScorer)->score($this->complete());

        $this->assertSame(100.0, $result['score']);
        $this->assertSame('No gaps found by the API checks.', $result['suggestions']);
        $this->assertSame(100, array_sum(array_column($result['checks'], 'max')));
    }

    public function test_empty_listing_scores_low_and_explains_every_gap(): void
    {
        $result = (new SheinListingQualityScorer)->score([]);

        $this->assertLessThan(5, $result['score']);
        foreach (['Title', 'Description', 'Key features', 'Images', 'Product attributes', 'Listing health', 'Catalog data'] as $name) {
            $this->assertStringContainsString("[{$name}]", $result['suggestions']);
        }
    }

    public function test_title_is_cut_at_the_shein_limit_before_scoring(): void
    {
        $c = $this->complete();
        $c['title'] = '5 Core '.str_repeat('Adjustable Speaker Stand ', 8);

        $facts = (new SheinListingQualityScorer)->facts($c);

        $this->assertSame(SheinListingQualityScorer::TITLE_MAX, mb_strlen($facts['title']));
    }

    public function test_inactive_sold_out_listing_loses_listing_health(): void
    {
        $c = $this->complete();
        $c['active'] = false;
        $c['stock'] = 0;

        $health = (new SheinListingQualityScorer)->score($c)['checks'][5];

        $this->assertSame('Listing health', $health['name']);
        $this->assertSame(5.0, $health['points']);
    }

    public function test_images_and_attributes_scale_with_count(): void
    {
        $scorer = new SheinListingQualityScorer;
        $c = $this->complete();
        $c['images'] = ['https://img.example.com/1.jpg', 'https://img.example.com/2.jpg'];
        $c['attributes'] = 3;

        $checks = array_column($scorer->score($c)['checks'], 'points', 'name');

        $this->assertSame(6.0, $checks['Images']);
        $this->assertSame(5.0, $checks['Product attributes']);
    }
}
