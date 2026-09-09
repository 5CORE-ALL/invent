<?php

namespace Tests\Unit;

use App\Models\AmazonOrder;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class AmazonOrderProductSalesTest extends TestCase
{
    public function test_ordered_product_sales_excludes_shipping_and_promo(): void
    {
        $raw = [
            'ItemPrice' => ['Amount' => 100],
            'ShippingPrice' => ['Amount' => 10],
            'GiftWrapPrice' => ['Amount' => 5],
            'PromotionDiscount' => ['Amount' => 8],
        ];

        $this->assertSame(92.0, AmazonOrder::orderedProductSalesFromItem(115, $raw));
    }

    public function test_ordered_product_sales_falls_back_to_stored_minus_ship_gift(): void
    {
        $raw = [
            'ShippingPrice' => ['Amount' => 10],
            'GiftWrapPrice' => ['Amount' => 2],
        ];

        $this->assertSame(88.0, AmazonOrder::orderedProductSalesFromItem(100, $raw));
    }

    public function test_pdt_utc_windows_use_us_pacific_dst_rules(): void
    {
        $windows = AmazonOrder::pacificPdtUtcWindows(
            new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC')),
            new \DateTimeImmutable('2026-12-31 00:00:00', new \DateTimeZone('UTC'))
        );

        $this->assertContains(['2026-03-08 10:00:00', '2026-11-01 09:00:00'], $windows);
    }

    public function test_sql_pacific_day_offset_matches_carbon(): void
    {
        $start = new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC'));
        $end = new \DateTimeImmutable('2026-12-31 00:00:00', new \DateTimeZone('UTC'));
        $windows = AmazonOrder::pacificPdtUtcWindows($start, $end);
        $sql = AmazonOrder::pacificDayFromUtcDatetimeSql('o.order_date', $start, $end);

        $this->assertStringContainsString("o.order_date >= '2026-03-08 10:00:00'", $sql);
        $this->assertStringContainsString("o.order_date < '2026-11-01 09:00:00'", $sql);

        $samples = [
            '2026-01-01 07:30:00' => '2025-12-31',
            '2026-03-08 09:59:59' => '2026-03-08',
            '2026-03-08 10:00:00' => '2026-03-08',
            '2026-07-04 07:00:00' => '2026-07-04',
            '2026-11-01 08:59:59' => '2026-11-01',
            '2026-11-01 09:00:00' => '2026-11-01',
        ];

        foreach ($samples as $utc => $pacificDay) {
            $this->assertSame(
                $pacificDay,
                \Carbon\Carbon::parse($utc, 'UTC')->timezone('America/Los_Angeles')->toDateString(),
                $utc
            );

            $inPdt = false;
            foreach ($windows as [$from, $to]) {
                if ($utc >= $from && $utc < $to) {
                    $inPdt = true;
                    break;
                }
            }
            $shifted = \Carbon\Carbon::parse($utc, 'UTC')->addHours($inPdt ? -7 : -8)->toDateString();
            $this->assertSame($pacificDay, $shifted, "SQL offset for {$utc}");
        }
    }

    public function test_daily_sales_l30_window_is_30_pacific_days_through_yesterday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', 'America/Los_Angeles'));
        try {
            [$start, $end] = AmazonOrder::dailySalesL30Window(30);
            $this->assertSame(
                '2026-08-11 00:00:00',
                $start->timezone('America/Los_Angeles')->format('Y-m-d H:i:s')
            );
            $this->assertSame(
                '2026-09-09 23:59:59',
                $end->timezone('America/Los_Angeles')->format('Y-m-d H:i:s')
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_units_sold_lookup_matches_product_sku_variants(): void
    {
        $map = AmazonOrder::indexUnitsSoldBySkuKeys([
            (object) ['sku' => 'SPKN GRN 4PCS', 'qty' => 2],
        ]);

        $this->assertSame(2, AmazonOrder::unitsSoldForProductSku('SPKN GRN 4PCS', $map));
        $this->assertSame(2, AmazonOrder::unitsSoldForProductSku('spkn grn 4pcs', $map));
        $this->assertSame(2, AmazonOrder::unitsSoldForProductSku('SPKNGRN4PCS', $map));
        $this->assertSame(0, AmazonOrder::unitsSoldForProductSku('OTHER SKU', $map));
    }
}
