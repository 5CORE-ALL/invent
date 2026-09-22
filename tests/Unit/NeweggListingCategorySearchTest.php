<?php

namespace Tests\Unit;

use App\Services\NeweggApiService;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingManagerEditorProfile;
use PHPUnit\Framework\TestCase;

class NeweggListingCategorySearchTest extends TestCase
{
    /**
     * @return list<array{id: string, path: string, name: string}>
     */
    private function leaves(): array
    {
        return [
            ['id' => '1508', 'path' => 'Apparel > Belts & Suspenders', 'name' => 'Belts & Suspenders'],
            ['id' => '397', 'path' => 'Computer & Electronics > Computer Speakers', 'name' => 'Computer Speakers'],
            ['id' => '214', 'path' => 'Computer & Electronics > Lighting Accessories', 'name' => 'Lighting Accessories'],
        ];
    }

    public function test_editor_treats_newegg_as_its_own_family(): void
    {
        $this->assertSame('newegg', ListingManagerEditorProfile::family('newegg'));
        $this->assertSame('newegg', ListingManagerEditorProfile::family('neweggb2c'));
        $this->assertSame('newegg', ListingManagerEditorProfile::family('neweggb2b'));

        $profile = ListingManagerEditorProfile::forChannel('Newegg');
        $this->assertTrue($profile['newegg']);
        $this->assertSame('newegg', $profile['family']);
        $this->assertSame('Search Newegg categories (e.g. speaker)', $profile['category_placeholder']);
    }

    public function test_keyword_search_matches_newegg_subcategory_name(): void
    {
        $rows = NeweggApiService::filterListingCategoryLeaves($this->leaves(), 'speaker');

        $this->assertCount(1, $rows);
        $this->assertSame('397', $rows[0]['id']);
        $this->assertSame('Computer Speakers', $rows[0]['name']);
    }

    public function test_numeric_id_search_returns_exact_subcategory(): void
    {
        $rows = NeweggApiService::filterListingCategoryLeaves($this->leaves(), '214');

        $this->assertSame('214', $rows[0]['id']);
        $this->assertSame('Lighting Accessories', $rows[0]['name']);
    }

    public function test_sku_and_placeholder_are_not_live_newegg_item_numbers(): void
    {
        $sku = 'LS100-6 RED';
        $this->assertFalse(ChannelListingRegistry::isLiveNeweggListingId('', $sku));
        $this->assertFalse(ChannelListingRegistry::isLiveNeweggListingId($sku, $sku));
        $this->assertFalse(ChannelListingRegistry::isLiveNeweggListingId('NE-abc123def456', $sku));
        $this->assertFalse(ChannelListingRegistry::isLiveNeweggListingId('2291326430', $sku));
        $this->assertFalse(ChannelListingRegistry::isLiveNeweggListingId('2PQCX3SPZ3QBF', $sku));
        $this->assertTrue(ChannelListingRegistry::isLiveNeweggListingId('9SIA12345ABC', $sku));
    }

    public function test_create_item_property_xml_uses_subcategory_wrapper(): void
    {
        $xml = NeweggApiService::subcategoryPropertyXml('Computer Speakers', [
            'Speaker_Brand' => '5 Core',
            'Speaker_Model' => 'LS100-6 RED',
        ]);

        $this->assertStringContainsString('<SubCategoryProperty><ComputerSpeakers>', $xml);
        $this->assertStringContainsString('<SpeakerBrand>5 Core</SpeakerBrand>', $xml);
        $this->assertStringContainsString('<SpeakerModel>LS100-6 RED</SpeakerModel>', $xml);
    }

    public function test_feed_result_parser_reads_errors_and_item_number(): void
    {
        $parsed = NeweggApiService::parseFeedResultPayload([
            'NeweggEnvelope' => [
                'Message' => [
                    'ProcessingReport' => [
                        'ProcessingSummary' => [
                            'SuccessCount' => '1',
                            'WithErrorCount' => '1',
                        ],
                        'Result' => [
                            [
                                'AdditionalInfo' => [
                                    'SellerPartNumber' => 'LS100-6 RED',
                                    'NeweggItemNumber' => '9SIA12345ABC',
                                ],
                            ],
                            [
                                'AdditionalInfo' => [
                                    'SellerPartNumber' => 'OTHER',
                                ],
                                'ErrorList' => [
                                    'ErrorDescription' => [
                                        'Error(s). Item not created.',
                                        'Manufacturer - The manufacturer does not exist in our system.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('9SIA12345ABC', $parsed['item_number']);
        $this->assertSame(1, $parsed['success_count']);
        $this->assertContains('Manufacturer - The manufacturer does not exist in our system.', $parsed['errors']);
    }
}
