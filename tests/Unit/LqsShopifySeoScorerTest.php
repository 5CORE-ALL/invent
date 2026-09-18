<?php

namespace Tests\Unit;

use App\Services\Lqs\LqsShopifySeoScorer;
use PHPUnit\Framework\TestCase;

class LqsShopifySeoScorerTest extends TestCase
{
    private LqsShopifySeoScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new LqsShopifySeoScorer();
    }

    public function test_seo_is_not_analyzed_without_keyphrase(): void
    {
        $result = $this->scorer->score([
            'title' => '5 Core 12 Inch Subwoofer',
            'seo_title' => '5 Core 12 Inch Subwoofer',
            'seo_description' => 'Buy a 12 inch subwoofer for car audio bass.',
            'body_html' => '<p>A 12 inch subwoofer for car audio.</p>',
            'keyphrase' => '',
        ]);

        $this->assertNull($result['seo_score']);
        $this->assertSame(LqsShopifySeoScorer::NA, $result['seo_rating']);
        $this->assertNotEmpty($result['findings']);
    }

    public function test_good_keyphrase_usage_scores_good_seo(): void
    {
        $body = <<<'HTML'
<p>This 12 inch subwoofer is built for clean car audio bass. However, it also works in a sealed box because the cone stays controlled.</p>
<p>Use this 12 inch subwoofer with a compatible amp. For example, pair it with a 4 ohm stable amplifier after you check the RMS rating.</p>
<ul>
<li>12 inch subwoofer cone</li>
<li>500W peak power</li>
</ul>
HTML;

        $result = $this->scorer->score([
            'title' => '12 inch subwoofer for car audio',
            'seo_title' => '12 inch subwoofer for car bass',
            'seo_description' => 'Shop this 12 inch subwoofer for car audio bass. It is built for sealed boxes and daily listening.',
            'body_html' => $body,
            'keyphrase' => '12 inch subwoofer',
            'image_alts' => ['12 inch subwoofer front view'],
        ]);

        $this->assertNotNull($result['seo_score']);
        $this->assertGreaterThanOrEqual(71, $result['seo_score']);
        $this->assertSame(LqsShopifySeoScorer::GOOD, $result['seo_rating']);
    }

    public function test_empty_body_is_not_analyzed_for_readability(): void
    {
        $result = $this->scorer->score([
            'title' => 'Speaker',
            'body_html' => '',
            'keyphrase' => 'speaker',
        ]);

        $this->assertNull($result['readability_score']);
        $this->assertSame(LqsShopifySeoScorer::NA, $result['readability_rating']);
    }

    public function test_long_sentences_need_readability_improvement(): void
    {
        $long = 'This product is designed to be used in a wide variety of installations where the installer wants a speaker that can handle a lot of power without becoming distorted even when the volume is turned up for a long time during weekend parties.';
        $result = $this->scorer->score([
            'title' => 'Speaker',
            'body_html' => "<p>{$long} {$long}</p><p>{$long}</p>",
            'keyphrase' => '',
        ]);

        $this->assertNotNull($result['readability_score']);
        $this->assertSame(LqsShopifySeoScorer::BAD, $result['readability_rating']);
    }

    public function test_short_sentences_and_lists_score_readable(): void
    {
        $html = <<<'HTML'
<h3>Why it works</h3>
<p>This speaker is easy to install. It also fits most standard boxes. However, check the cutout first.</p>
<p>Use it for daily driving. Then add an amp if you want more punch.</p>
<ul>
<li>Easy wiring</li>
<li>Strong bass</li>
<li>Compact frame</li>
</ul>
HTML;

        $result = $this->scorer->score([
            'title' => 'Car speaker',
            'body_html' => $html,
        ]);

        $this->assertNotNull($result['readability_score']);
        $this->assertContains($result['readability_rating'], [
            LqsShopifySeoScorer::GOOD,
            LqsShopifySeoScorer::OK,
        ]);
        $this->assertGreaterThanOrEqual(41, $result['readability_score']);
    }

    public function test_extracts_keyphrase_from_yoast_payload(): void
    {
        $this->assertSame(
            'car subwoofer',
            $this->scorer->extractKeyphrase([
                'title' => 'Car Subwoofer',
                'primary_focus_keyword' => 'car subwoofer',
            ])
        );
    }
}
