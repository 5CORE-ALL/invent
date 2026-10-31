<?php

namespace Tests\Unit;

use App\Support\Marketplace\VariationListingContent;
use PHPUnit\Framework\TestCase;

class VariationListingAplusTest extends TestCase
{
    private function pack(string $text, string $aplus): array
    {
        return ['description' => $text, 'aplus' => $aplus];
    }

    public function test_a_plus_is_sent_as_html_to_ebay_when_saved(): void
    {
        $pack = $this->pack('Plain text', '<div><h2>Big</h2><p>Page</p></div>');

        $this->assertSame('<div><h2>Big</h2><p>Page</p></div>', VariationListingContent::pickDescription($pack, 'ebaytwo'));
        $this->assertSame('<div><h2>Big</h2><p>Page</p></div>', VariationListingContent::pickDescription($pack, 'ebay'));
    }

    public function test_marketplaces_without_html_keep_the_text_description(): void
    {
        $pack = $this->pack('Plain text', '<div>Page</div>');

        $this->assertSame('Plain text', VariationListingContent::pickDescription($pack, 'temu'));
        $this->assertSame('Plain text', VariationListingContent::pickDescription($pack, 'amazon'));
    }

    public function test_the_text_description_is_used_when_there_is_no_a_plus(): void
    {
        $pack = $this->pack('Plain text', '');

        $this->assertSame('Plain text', VariationListingContent::pickDescription($pack, 'ebay'));
    }

    public function test_a_plus_text_is_used_when_there_is_no_text_description(): void
    {
        $pack = $this->pack('', '<p>First</p><p>Second &amp; more</p>');

        $this->assertSame('<p>First</p><p>Second &amp; more</p>', VariationListingContent::pickDescription($pack, 'ebay'));
        $this->assertSame("First\nSecond & more", VariationListingContent::pickDescription($pack, 'temu'));
    }

    public function test_the_active_channel_follows_the_innermost_run(): void
    {
        VariationListingContent::activate('ebaytwo');
        $this->assertSame('ebaytwo', VariationListingContent::currentChannel());
        VariationListingContent::activate();
        $this->assertSame('', VariationListingContent::currentChannel());
        VariationListingContent::deactivate();
        $this->assertSame('ebaytwo', VariationListingContent::currentChannel());
        VariationListingContent::deactivate();
        $this->assertFalse(VariationListingContent::active());
    }
}
