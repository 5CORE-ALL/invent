<?php

namespace Tests\Unit;

use App\Services\Support\EbayTradingReviseItem;
use PHPUnit\Framework\TestCase;

class EbayHero2VariationPictureTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function variationGetItem(): array
    {
        return [
            'Ack' => 'Success',
            'Item' => [
                'ItemID' => '365518123456',
                'PictureDetails' => [
                    'PictureURL' => ['https://parent.example/parent.jpg'],
                ],
                'Variations' => [
                    'Variation' => [
                        [
                            'SKU' => 'CS-04.2W',
                            'VariationSpecifics' => [
                                'NameValueList' => [
                                    ['Name' => 'Color', 'Value' => 'Black'],
                                    ['Name' => 'Size', 'Value' => '6.5"'],
                                ],
                            ],
                        ],
                        [
                            'SKU' => 'CS-04.2W-WHT',
                            'VariationSpecifics' => [
                                'NameValueList' => [
                                    ['Name' => 'Color', 'Value' => 'White'],
                                    ['Name' => 'Size', 'Value' => '6.5"'],
                                ],
                            ],
                        ],
                    ],
                    'Pictures' => [
                        'VariationSpecificName' => 'Color',
                        'VariationSpecificPictureSet' => [
                            [
                                'VariationSpecificValue' => 'Black',
                                'PictureURL' => ['https://i.ebayimg.com/old-black.jpg'],
                            ],
                            [
                                'VariationSpecificValue' => 'White',
                                'PictureURL' => 'https://i.ebayimg.com/old-white.jpg',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_plan_updates_only_matching_sku_color(): void
    {
        $plan = EbayTradingReviseItem::variationPicturePlan(
            $this->variationGetItem(),
            'CS 04.2W',
            ['https://cdn.example/hero2-black.jpg']
        );

        $this->assertTrue($plan['ok']);
        $this->assertSame('Color', $plan['name']);
        $this->assertSame('Black', $plan['variation_value']);
        $this->assertFalse($plan['not_variation'] ?? false);
    }

    public function test_merge_keeps_sibling_picture_sets(): void
    {
        $state = EbayTradingReviseItem::extractVariationPictureState($this->variationGetItem());
        $merged = EbayTradingReviseItem::mergeVariationPictureSets(
            $state['sets'],
            'Black',
            ['https://i.ebayimg.com/new-black.jpg']
        );

        $byValue = [];
        foreach ($merged as $set) {
            $byValue[$set['value']] = $set['urls'];
        }

        $this->assertSame(['https://i.ebayimg.com/new-black.jpg', 'https://i.ebayimg.com/old-black.jpg'], $byValue['Black']);
        $this->assertSame(['https://i.ebayimg.com/old-white.jpg'], $byValue['White']);
    }

    public function test_revise_xml_has_no_parent_picture_details(): void
    {
        $xml = EbayTradingReviseItem::buildReviseVariationPicturesRequestXml(
            'token',
            '365518123456',
            'Color',
            [
                ['value' => 'Black', 'urls' => ['https://i.ebayimg.com/new-black.jpg']],
                ['value' => 'White', 'urls' => ['https://i.ebayimg.com/old-white.jpg']],
            ]
        );

        $this->assertStringContainsString('ReviseFixedPriceItemRequest', $xml);
        $this->assertStringContainsString('<VariationSpecificName>Color</VariationSpecificName>', $xml);
        $this->assertStringContainsString('<VariationSpecificValue>Black</VariationSpecificValue>', $xml);
        $this->assertStringContainsString('<VariationSpecificValue>White</VariationSpecificValue>', $xml);
        $this->assertStringNotContainsString('PictureDetails', $xml);
        $this->assertStringNotContainsString('GalleryURL', $xml);
    }

    public function test_unknown_sku_does_not_plan_parent_update(): void
    {
        $plan = EbayTradingReviseItem::variationPicturePlan(
            $this->variationGetItem(),
            'NOT-A-CHILD',
            ['https://cdn.example/hero2.jpg']
        );

        $this->assertFalse($plan['ok']);
        $this->assertStringContainsString('Parent image was not changed', $plan['message']);
    }

    public function test_single_sku_listing_is_marked_not_variation(): void
    {
        $plan = EbayTradingReviseItem::variationPicturePlan([
            'Item' => [
                'ItemID' => '111',
                'PictureDetails' => ['PictureURL' => 'https://parent.example/a.jpg'],
            ],
        ], 'SINGLE-SKU', ['https://cdn.example/hero2.jpg']);

        $this->assertFalse($plan['ok']);
        $this->assertTrue($plan['not_variation']);
    }
}
