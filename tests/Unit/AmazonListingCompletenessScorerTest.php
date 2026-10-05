<?php

namespace Tests\Unit;

use App\Services\Lqs\AmazonListingCompletenessScorer;
use PHPUnit\Framework\TestCase;

class AmazonListingCompletenessScorerTest extends TestCase
{
    private AmazonListingCompletenessScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new AmazonListingCompletenessScorer();
    }

    public function test_complete_amazon_listing_scores_100(): void
    {
        $result = $this->scorer->score($this->listing());

        $this->assertSame(100, $result['score']);
        $this->assertSame('A', $result['grade']);
        $this->assertSame([], $result['missing']);
    }

    public function test_amazon_example_gaps_drop_brand_title_and_partial_attributes(): void
    {
        $result = $this->scorer->score($this->listing([
            'title' => 'Blue Guitar Capo 1 Piece',
            'brand' => '',
            'attributes' => [
                'color' => 'Blue',
                'material' => '',
                'size' => '',
                'dimensions' => '',
                'included' => '',
                'model' => 'X1',
            ],
        ]));

        $this->assertSame(73, $result['score']);
        $this->assertSame('B', $result['grade']);
        $this->assertContains('Brand', $result['missing']);
        $this->assertContains('Title starts with brand', $result['missing']);
        $this->assertContains('Key attributes (2/6)', $result['missing']);
    }

    public function test_thin_listing_loses_images_aplus_and_extra_bullets(): void
    {
        $result = $this->scorer->score($this->listing([
            'search_terms' => '',
            'aplus' => '',
            'bullets' => ['One feature only'],
            'images' => ['https://m.media-amazon.com/images/I/abc._SL75_.jpg'],
            'attributes' => [
                'color' => '',
                'material' => '',
                'size' => '',
                'dimensions' => '',
                'included' => '',
                'model' => '',
            ],
        ]));

        $this->assertSame(35, $result['score']);
        $this->assertSame('E', $result['grade']);
        $this->assertContains('A+ content', $result['missing']);
        $this->assertContains('4+ images', $result['missing']);
        $this->assertContains('Main image zoom', $result['missing']);
    }

    public function test_stored_listing_reads_image_urls_bullets_and_aplus(): void
    {
        $result = $this->scorer->scoreStored((object) [
            'item_name' => '5 Core Blue Capo',
            'brand' => '5 Core',
            'generic_keyword' => 'guitar capo',
            'product_description' => 'A spring capo for acoustic and electric guitars.',
            'bullet_point' => json_encode(['Steel spring', 'Silicone pad', 'One piece']),
            'item_type_keyword' => 'guitar-capos',
            'product_type' => 'MUSICAL_INSTRUMENTS',
            'color' => 'Blue',
            'material' => 'Metal',
            'size' => '1 Pc',
            'item_dimensions' => ['length' => 1],
            'included_components' => 'Capo',
            'manufacturer' => '5 Core',
            'thumbnail_image' => 'https://m.media-amazon.com/images/I/thumb._SL75_.jpg',
            'raw_data' => [
                'image-url' => 'https://m.media-amazon.com/images/I/main.jpg',
                'image-url-1' => 'https://m.media-amazon.com/images/I/side.jpg',
                'image-url-2' => 'https://m.media-amazon.com/images/I/use.jpg',
                'image-url-3' => 'https://m.media-amazon.com/images/I/size.jpg',
            ],
        ], '<div>A+ comparison chart</div>');

        $this->assertSame(100, $result['score']);
        $this->assertSame('A', $result['grade']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function listing(array $overrides = []): array
    {
        $base = [
            'title' => '5 Core Blue Guitar Capo 1 Piece',
            'brand' => '5 Core',
            'search_terms' => 'guitar capo blue',
            'description' => 'Spring capo for acoustic and electric guitars.',
            'aplus' => '<div>Brand story and comparison chart</div>',
            'bullets' => ['Steel spring', 'Silicone pad', 'Fits most necks', 'One piece'],
            'browse_node' => 'guitar-capos',
            'images' => [
                'https://m.media-amazon.com/images/I/main.jpg',
                'https://m.media-amazon.com/images/I/side.jpg',
                'https://m.media-amazon.com/images/I/use.jpg',
                'https://m.media-amazon.com/images/I/size.jpg',
            ],
            'attributes' => [
                'color' => 'Blue',
                'material' => 'Metal',
                'size' => '1 Pc',
                'dimensions' => '2 x 1 x 1 in',
                'included' => 'Capo',
                'model' => 'CAPO-1',
            ],
        ];

        foreach ($overrides as $key => $value) {
            $base[$key] = $value;
        }

        return $base;
    }
}
