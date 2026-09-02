<?php

namespace Tests\Unit;

use App\Support\Marketplace\Temu3OrderSheet;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class Temu3OrderSheetTest extends TestCase
{
    public function test_maps_seller_center_headers(): void
    {
        $this->assertSame('order_id', Temu3OrderSheet::normalizeHeader('Order ID'));
        $this->assertSame('contribution_sku', Temu3OrderSheet::normalizeHeader('contribution sku'));
        $this->assertSame('base_price_total', Temu3OrderSheet::normalizeHeader('goods base price'));
        $this->assertSame('quantity_canceled', Temu3OrderSheet::normalizeHeader('quantity canceled'));
        $this->assertSame('purchase_date', Temu3OrderSheet::normalizeHeader('purchase date'));
    }

    public function test_parses_ist_purchase_date_into_pacific(): void
    {
        $parsed = Temu3OrderSheet::parsePurchaseDate('Sep 3, 2026, 2:04 am IST(UTC+5)');

        $this->assertInstanceOf(Carbon::class, $parsed);
        $this->assertSame('America/Los_Angeles', $parsed->timezoneName);
        $this->assertSame('2026-09-02', $parsed->toDateString());
    }

    public function test_sanitizes_goods_base_price(): void
    {
        $this->assertSame(9.64, Temu3OrderSheet::sanitizePrice('$9.64'));
        $this->assertSame(31.01, Temu3OrderSheet::sanitizePrice('$31.01 '));
    }

    public function test_excludes_canceled_and_zero_qty(): void
    {
        $this->assertTrue(Temu3OrderSheet::shouldExcludeFromSales([
            'order_status' => 'Canceled',
            'quantity_purchased' => 1,
        ]));
        $this->assertTrue(Temu3OrderSheet::shouldExcludeFromSales([
            'order_status' => 'Unshipped',
            'quantity_purchased' => 0,
        ]));
        $this->assertFalse(Temu3OrderSheet::shouldExcludeFromSales([
            'order_status' => 'Unshipped',
            'quantity_purchased' => 1,
            'quantity_canceled' => 0,
        ]));
    }

    public function test_parses_sample_seller_center_export(): void
    {
        $path = dirname(__DIR__, 2).'/temu3price.txt';
        $this->assertFileExists($path);

        $fh = fopen($path, 'rb');
        $this->assertNotFalse($fh);
        $headers = fgetcsv($fh, 0, "\t");
        $this->assertIsArray($headers);
        $normalized = array_map([Temu3OrderSheet::class, 'normalizeHeader'], $headers);
        $this->assertContains('order_id', $normalized);
        $this->assertContains('contribution_sku', $normalized);
        $this->assertContains('base_price_total', $normalized);

        $mapped = [];
        while (($row = fgetcsv($fh, 0, "\t")) !== false) {
            $rowData = array_pad(array_slice($row, 0, count($normalized)), count($normalized), null);
            $data = array_combine($normalized, $rowData);
            $insert = Temu3OrderSheet::mapInsertRow($data);
            if ($insert !== null) {
                $mapped[] = $insert;
            }
        }
        fclose($fh);

        $this->assertCount(4, $mapped);
        $skus = array_column($mapped, 'contribution_sku');
        $this->assertContains('WF 4INCH', $skus);
        $this->assertContains('KS Z1 WH', $skus);
        $this->assertContains('KS 2X BLK+KBB BLK HD', $skus);

        $first = $mapped[0];
        $this->assertSame('PO-211-10467286595191067', $first['order_id']);
        $this->assertSame('WF 4INCH', $first['contribution_sku']);
        $this->assertSame('170467251982528', $first['sku_id']);
        $this->assertSame(1, $first['quantity_purchased']);
        $this->assertSame(9.64, $first['base_price_total']);
        $this->assertNotEmpty($first['purchase_date']);
        $this->assertSame('2026-09-02', substr((string) $first['purchase_date'], 0, 10));
    }
}
