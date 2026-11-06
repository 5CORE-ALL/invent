<?php

namespace Tests\Unit;

use App\Services\Lqs\AlibabaListingQualityScorer;
use PHPUnit\Framework\TestCase;

class AlibabaListingQualityScorerTest extends TestCase
{
    private function complete(): array
    {
        return [
            'title' => '5 Core Speaker Stand Adjustable Tripod PA Speakers Holder 35mm Pole Mount Heavy Duty',
            'description' => '<p>'.str_repeat('Heavy duty steel stand with a 35mm insert that fits most PA speakers. ', 10).'</p>',
            'images' => array_map(fn ($i) => "https://sc04.alicdn.com/img{$i}.jpg", range(1, 6)),
            'attribute_count' => 9,
            'price' => 18.53,
            'stock' => 40,
            'status' => 'approved',
            'published' => true,
            'displayed' => true,
            'length' => 36, 'width' => 9, 'height' => 5.5, 'weight' => 10.4,
            'origin' => 'China',
        ];
    }

    public function test_complete_listing_scores_100(): void
    {
        $result = (new AlibabaListingQualityScorer)->score($this->complete());

        $this->assertSame(100.0, $result['score']);
        $this->assertSame('No gaps found by the API checks.', $result['suggestions']);
        $this->assertSame(100, array_sum(array_column($result['checks'], 'max')));
    }

    public function test_empty_listing_scores_low_and_explains_every_gap(): void
    {
        $result = (new AlibabaListingQualityScorer)->score([]);

        $this->assertSame(0.0, $result['score']);
        $this->assertStringContainsString('subject (title) is missing', $result['suggestions']);
        $this->assertStringContainsString('not approved/online', $result['suggestions']);
    }

    public function test_hidden_out_of_stock_listing_loses_listing_health(): void
    {
        $data = $this->complete();
        $data['displayed'] = false;
        $data['stock'] = 0;

        // -4 hidden, -8 no stock
        $this->assertSame(88.0, (new AlibabaListingQualityScorer)->score($data)['score']);
    }

    public function test_few_images_and_attributes_lose_points(): void
    {
        $data = $this->complete();
        $data['images'] = array_slice($data['images'], 0, 3); // 10/20
        $data['attribute_count'] = 2;                         // 2/10

        $this->assertSame(82.0, (new AlibabaListingQualityScorer)->score($data)['score']);
    }
}
