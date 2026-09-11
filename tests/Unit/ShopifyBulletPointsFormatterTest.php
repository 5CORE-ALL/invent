<?php

namespace Tests\Unit;

use App\Services\Support\ShopifyBulletPointsFormatter;
use PHPUnit\Framework\TestCase;

class ShopifyBulletPointsFormatterTest extends TestCase
{
    public function test_extracts_heading_then_paragraph_pairs_from_top_of_description(): void
    {
        $html = <<<'HTML'
<p><strong>500W 6.5 INCH SUBWOOFER</strong></p>
<p>Built to add stronger low-frequency impact to your car audio system, this 6.5 inch subwoofer is made for bass-focused upgrades, replacement and custom setups.</p>
<p><strong>BUILT FOR CAR AUDIO BASS</strong></p>
<p>Designed as a dedicated bass speaker, this 6.5 woofer helps add low-end depth to music, making it a must have if you like deep bass in their music.</p>
<p><strong>4 OHM IMPEDANCE &amp; POLYPROPYLENE CONE</strong></p>
<p>The 4 Ohm design supports integration with compatible car audio systems, while the polypropylene cone provides a durable speaker surface.</p>
<p><strong>20 OZ Y30 MAGNET</strong></p>
<p>The Y30 magnet provides the magnetic force needed to control cone movement, supporting responsive bass reproduction in your car audio system.</p>
<p><strong>1 INCH DUAL-LAYER ASV VOICE COIL</strong></p>
<p>The dual-layer ASV voice coil is designed to handle heat and power, supporting consistent performance during extended bass-heavy listening.</p>
<h3>Product Description</h3>
<p>Speaker Type: Woofer</p>
<p>Woofer Size: 6.5 inch</p>
HTML;

        $extracted = ShopifyBulletPointsFormatter::extractBulletPointsForImport($html);

        $this->assertSame('heading_body_pairs', $extracted['format']);
        $this->assertCount(5, $extracted['bullets']);
        $this->assertStringStartsWith('500W 6.5 INCH SUBWOOFER - Built to add', $extracted['bullets'][0]);
        $this->assertStringStartsWith('BUILT FOR CAR AUDIO BASS - Designed as a dedicated bass speaker', $extracted['bullets'][1]);
        $this->assertStringStartsWith('4 OHM IMPEDANCE & POLYPROPYLENE CONE - The 4 Ohm design', $extracted['bullets'][2]);
        $this->assertStringStartsWith('20 OZ Y30 MAGNET - The Y30 magnet', $extracted['bullets'][3]);
        $this->assertStringStartsWith('1 INCH DUAL-LAYER ASV VOICE COIL - The dual-layer ASV', $extracted['bullets'][4]);
    }

    public function test_extracts_h3_plus_paragraph_pairs(): void
    {
        $html = <<<'HTML'
<h3>500W 6.5 INCH SUBWOOFER</h3>
<p>Built to add stronger low-frequency impact to your car audio system for bass-focused upgrades.</p>
<h3>BUILT FOR CAR AUDIO BASS</h3>
<p>Designed as a dedicated bass speaker, this 6.5 woofer helps add low-end depth to music.</p>
HTML;

        $extracted = ShopifyBulletPointsFormatter::extractBulletPointsForImport($html);

        $this->assertSame('heading_body_pairs', $extracted['format']);
        $this->assertCount(2, $extracted['bullets']);
        $this->assertStringContainsString('500W 6.5 INCH SUBWOOFER - ', $extracted['bullets'][0]);
    }

    public function test_prefers_description_pairs_over_short_spec_rows(): void
    {
        $headingBody = ShopifyBulletPointsFormatter::extractBulletPointsForImport(
            '<p><strong>500W 6.5 INCH SUBWOOFER</strong></p><p>Built to add stronger low-frequency impact to your car audio system, this 6.5 inch subwoofer is made for bass-focused upgrades.</p><p><strong>BUILT FOR CAR AUDIO BASS</strong></p><p>Designed as a dedicated bass speaker, this 6.5 woofer helps add low-end depth to music.</p>'
        );
        $specs = [
            'bullets' => ['Speaker Type: Woofer', 'Woofer Size: 6.5 inch', 'RMS: 50W', 'Peak Power: 500W', 'Voice Coil Size: 26mm (1")'],
            'format' => 'legacy_text_bullets',
            'confidence' => 65,
        ];

        $chosen = ShopifyBulletPointsFormatter::preferExtract($specs, $headingBody);

        $this->assertSame('heading_body_pairs', $chosen['format']);
        $this->assertStringContainsString('500W 6.5 INCH SUBWOOFER', $chosen['bullets'][0]);
    }

    public function test_extracts_inline_strong_pairs_from_one_opening_paragraph(): void
    {
        $html = <<<'HTML'
<p dir="ltr" role="presentation"><strong>500W 6.5 INCH SUBWOOFER - </strong><span>Built to add stronger low-frequency impact to your car audio system, this 6.5 inch subwoofer is made for bass-focused upgrades, replacement and custom setups.<br><br></span><strong>BUILT FOR CAR AUDIO BASS -</strong><span> Designed as a dedicated bass speaker, this 6.5 subwoofer helps add low-end depth to music, making it a must have who likes deep bass in their music.<br><br></span><strong>4 OHM IMPEDANCE &amp; POLYPROPYLENE CONE -</strong><span><strong> </strong>The 4 Ohm design supports integration with compatible car audio systems, while the polypropylene cone provides a durable speaker surface.<br><br></span><strong>20 OZ Y30 MAGNET -</strong><span><strong> </strong>The Y30 magnet provides the magnetic force needed to control cone movement, supporting responsive bass reproduction in your car audio system.<br><br></span><strong>1 INCH DUAL-LAYER ASV VOICE COIL -</strong><span><strong> </strong>The dual-layer ASV voice coil is designed to handle heat and power, supporting consistent performance during extended bass-heavy listening.<br></span></p>
<h2>6.5 Inch Subwoofer Description</h2>
<ul>
<li>Speaker Type: Woofer</li>
<li>Woofer Size: 6.5 Inch</li>
<li>RMS: 50W</li>
<li>Peak Power: 500W</li>
<li>Voice Coil Size: 25MM (1")</li>
</ul>
HTML;

        $extracted = ShopifyBulletPointsFormatter::extractBulletPointsForImport($html);

        $this->assertSame('heading_body_pairs', $extracted['format']);
        $this->assertCount(5, $extracted['bullets']);
        $this->assertStringStartsWith('500W 6.5 INCH SUBWOOFER - Built to add', $extracted['bullets'][0]);
        $this->assertStringStartsWith('BUILT FOR CAR AUDIO BASS - Designed as a dedicated bass speaker', $extracted['bullets'][1]);
        $this->assertStringStartsWith('4 OHM IMPEDANCE & POLYPROPYLENE CONE - The 4 Ohm design', $extracted['bullets'][2]);
        $this->assertStringStartsWith('20 OZ Y30 MAGNET - The Y30 magnet', $extracted['bullets'][3]);
        $this->assertStringStartsWith('1 INCH DUAL-LAYER ASV VOICE COIL - The dual-layer ASV', $extracted['bullets'][4]);
    }

    public function test_prefers_inline_description_pairs_over_spec_list_extract(): void
    {
        $inline = ShopifyBulletPointsFormatter::extractBulletPointsForImport(
            '<p><strong>500W 6.5 INCH SUBWOOFER - </strong><span>Built to add stronger low-frequency impact to your car audio system, this 6.5 inch subwoofer is made for bass-focused upgrades.</span><strong>BUILT FOR CAR AUDIO BASS -</strong><span> Designed as a dedicated bass speaker, this 6.5 woofer helps add low-end depth to music.</span></p><ul><li>Speaker Type: Woofer</li><li>Woofer Size: 6.5 Inch</li></ul>'
        );
        $specs = [
            'bullets' => ['Speaker Type: Woofer', 'Woofer Size: 6.5 inch', 'RMS: 50W', 'Peak Power: 500W', 'Voice Coil Size: 25MM (1")'],
            'format' => 'top_list',
            'confidence' => 68,
        ];

        $chosen = ShopifyBulletPointsFormatter::preferExtract($specs, $inline);

        $this->assertSame('heading_body_pairs', $chosen['format']);
        $this->assertStringContainsString('500W 6.5 INCH SUBWOOFER', $chosen['bullets'][0]);
    }
}
