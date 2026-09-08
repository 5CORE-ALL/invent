<?php

namespace Tests\Unit;

use App\Services\PrcCprReportService;
use PHPUnit\Framework\TestCase;

class PrcCprReportServiceTest extends TestCase
{
    private PrcCprReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PrcCprReportService;
    }

    public function test_temu2_display_price_adds_shipping_below_threshold(): void
    {
        $this->assertSame(0.0, PrcCprReportService::temu2DisplayPrice(0));
        $this->assertSame(22.98, PrcCprReportService::temu2DisplayPrice(19.99));
        $this->assertSame(29.98, PrcCprReportService::temu2DisplayPrice(26.99));
        $this->assertSame(27.00, PrcCprReportService::temu2DisplayPrice(27.00));
    }

    public function test_compare_channels_exclude_requested_marketplaces(): void
    {
        $keys = array_map(
            static fn (array $channel) => $channel['key'],
            PrcCprReportService::compareChannels()
        );

        foreach (['temu', 'doba', 'depop', 'sb2b', 'shopifyb2b', 'wayfair', 'faire'] as $excluded) {
            $this->assertNotContains($excluded, $keys);
        }

        $this->assertContains('amazon', $keys);
        $this->assertContains('shopify', $keys);
        $this->assertNotContains('temu2', $keys);
        $this->assertTrue(PrcCprReportService::isExcludedChannel('Shopify B2B'));
        $this->assertTrue(PrcCprReportService::isExcludedChannel('temu'));
        $this->assertFalse(PrcCprReportService::isExcludedChannel('amazon'));
    }

    public function test_normalize_requested_skus_drops_parents_and_blanks(): void
    {
        $this->assertSame(
            ['ABC-1', 'XYZ'],
            $this->service->normalizeRequestedSkus([' ABC-1 ', 'PARENT FOO', '', 'XYZ', 'ABC-1'])
        );
    }

    public function test_compose_product_uses_temu2_header_and_other_channel_rows(): void
    {
        $product = $this->service->composeProduct(
            'ABC-1',
            ['ABC-1' => ['price' => 19.99, 'buyer_link' => 'https://temu2.example/abc']],
            [
                'amazon' => ['ABC-1' => 24.99],
                'ebay1' => ['ABC-1' => 22.5],
            ],
            [
                'amazon' => ['ABC-1' => 'https://amazon.example/abc'],
                'ebay1' => ['ABC-1' => 'https://ebay.example/abc'],
            ]
        );

        $this->assertSame('ABC-1', $product['sku']);
        $this->assertSame(19.99, $product['temu2']['price']);
        $this->assertSame('https://temu2.example/abc', $product['temu2']['buyer_link']);

        $byKey = [];
        foreach ($product['channels'] as $row) {
            $byKey[$row['key']] = $row;
        }

        $this->assertSame(24.99, $byKey['amazon']['price']);
        $this->assertSame('https://amazon.example/abc', $byKey['amazon']['buyer_link']);
        $this->assertSame(22.5, $byKey['ebay1']['price']);
        $this->assertArrayNotHasKey('tiktok', $byKey);
        $this->assertArrayNotHasKey('temu', $byKey);
        $this->assertArrayNotHasKey('doba', $byKey);
        $this->assertArrayNotHasKey('faire', $byKey);
        $this->assertArrayNotHasKey('sb2b', $byKey);
    }

    public function test_excel_rows_repeat_temu2_header_on_each_channel_row(): void
    {
        $rows = PrcCprReportService::excelRows([
            [
                'sku' => 'ABC-1',
                'temu2' => ['price' => 19.99, 'buyer_link' => 'https://temu2.example/abc'],
                'channels' => [
                    ['channel' => 'Amazon', 'price' => 24.99, 'buyer_link' => 'https://amazon.example/abc'],
                    ['channel' => 'Ebay1', 'price' => 22.5, 'buyer_link' => ''],
                ],
            ],
        ]);

        $this->assertSame(
            ['SKU', 'Temu2 Price', 'Temu2 Buyer Link', 'Channel', 'Channel Price', 'Channel Buyer Link'],
            $rows[0]
        );
        $this->assertSame(
            ['ABC-1', 19.99, 'https://temu2.example/abc', 'Amazon', 24.99, 'https://amazon.example/abc'],
            $rows[1]
        );
        $this->assertSame(
            ['ABC-1', 19.99, 'https://temu2.example/abc', 'Ebay1', 22.5, ''],
            $rows[2]
        );
    }
}
