<?php

namespace Tests\Unit;

use App\Services\Wayfair\WayfairBaseCostPullService;
use PHPUnit\Framework\TestCase;

class WayfairBaseCostPullServiceTest extends TestCase
{
    public function test_reads_current_base_cost_from_partner_home_export(): void
    {
        $export = (new WayfairBaseCostPullService())->readExport(
            dirname(__DIR__, 2).'/resources/wayfair/cost-change-template.xlsx'
        );

        $this->assertNotEmpty($export);
        // Partner Home's Base Cost for this SKU is 41.98 (its PO line shows 29.39).
        $this->assertSame(41.98, $export['WF 8140 DBL D2']['cost']);
        $this->assertSame('Live', $export['WF 8140 DBL D2']['status']);
        $this->assertSame(40.31, $export['MS 080 WH 4PC']['cost']);
    }

    public function test_unreadable_export_is_an_error(): void
    {
        $this->expectException(\RuntimeException::class);
        (new WayfairBaseCostPullService())->readExport('/nonexistent/export.xlsx');
    }
}
