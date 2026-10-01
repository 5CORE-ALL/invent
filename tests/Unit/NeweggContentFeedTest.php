<?php

namespace Tests\Unit;

use App\Services\NeweggApiService;
use Tests\TestCase;

class NeweggContentFeedTest extends TestCase
{
    public function test_image_urls_are_cleaned_for_newegg(): void
    {
        $this->assertSame(
            'https://cdn.shopify.com/s/files/1/0428/4923/9201/files/06_c0ab.jpg',
            NeweggApiService::neweggImageUrl('https://cdn.shopify.com/s/files/1/0428/4923/9201/files/06_c0ab.jpg?v=1712345678')
        );
        $this->assertSame('', NeweggApiService::neweggImageUrl('https://cdn.example.com/a.png'));
        $this->assertSame('', NeweggApiService::neweggImageUrl('not a url'));
        $this->assertSame('https://img.example.com/get?id=55', NeweggApiService::neweggImageUrl('https://img.example.com/get?id=55'));
    }

    public function test_content_feed_uses_documented_actions(): void
    {
        $xml = NeweggApiService::buildItemContentFeedXml('GSS AL SLV', [
            'WebsiteShortTitle' => 'Guitar Stand',
            'ProductDescription' => '<p>Strong</p><colgroup></colgroup>',
            'BulletDescription' => "One\nTwo",
            'ItemImages' => [
                'https://cdn.shopify.com/s/files/1/x/a.jpg?v=1',
                ['ImageUrl' => 'https://cdn.shopify.com/s/files/1/x/b.jpg'],
            ],
        ]);

        $this->assertStringContainsString('<DocumentVersion>1.0</DocumentVersion>', $xml);
        $this->assertStringContainsString('<Action>Update Item</Action>', $xml);
        $this->assertStringContainsString('<Action>Replace Image</Action>', $xml);
        $this->assertStringNotContainsString('UpdateItem<', $xml);
        $this->assertStringContainsString('<BulletDescription><![CDATA[One^^Two]]></BulletDescription>', $xml);
        $this->assertStringContainsString('<ProductDescription><![CDATA[<p>Strong</p>]]></ProductDescription>', $xml);
        $this->assertStringContainsString('<ImageUrl>https://cdn.shopify.com/s/files/1/x/a.jpg</ImageUrl><IsPrimary>true</IsPrimary>', $xml);
        $this->assertStringContainsString('<ImageUrl>https://cdn.shopify.com/s/files/1/x/b.jpg</ImageUrl><IsPrimary>false</IsPrimary>', $xml);
        $this->assertStringContainsString('<ActivationMark>True</ActivationMark>', $xml);
        $this->assertSame(2, substr_count($xml, '<SellerPartNumber>GSS AL SLV</SellerPartNumber>'));
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_images_only_feed_has_single_replace_image_row(): void
    {
        $xml = NeweggApiService::buildItemContentFeedXml('SKU1', [
            'ItemImages' => ['https://cdn.example.com/a.jpg'],
            'ImageMode' => 'append',
        ]);

        $this->assertStringNotContainsString('Update Item', $xml);
        $this->assertStringContainsString('<Action>Update/Append Image</Action>', $xml);
        $this->assertSame('', NeweggApiService::buildItemContentFeedXml('SKU1', ['ItemImages' => ['https://cdn.example.com/a.png']]));
    }
}
