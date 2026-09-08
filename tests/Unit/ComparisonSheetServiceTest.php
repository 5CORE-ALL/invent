<?php

namespace Tests\Unit;

use App\Models\ComparisonData;
use App\Services\ComparisonSheetService;
use PHPUnit\Framework\TestCase;

class ComparisonSheetServiceTest extends TestCase
{
    private ComparisonSheetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ComparisonSheetService;
    }

    public function test_default_template_has_no_meaningful_content(): void
    {
        $cells = $this->service->ensureLeadColumns(ComparisonData::defaultSheetCells());

        $this->assertSame(0, $this->service->filledCellScore($cells));
        $this->assertFalse($this->service->hasMeaningfulContent($cells));
        $this->assertSame(0, $this->service->countNamedSupplierColumns($cells));
    }

    public function test_person_name_review_row_counts_as_supplier_names(): void
    {
        $cells = $this->service->ensureLeadColumns(ComparisonData::defaultSheetCells());
        $specCol = $this->service->detectSpecColumnIndex($cells);
        $nameRow = $this->service->findRowIndexByLabel($cells, 'person name review', $specCol);
        $this->assertNotNull($nameRow);

        $firstSupplier = $this->service->getFirstSupplierColumnIndex($cells, $specCol);
        $cells[$nameRow][$firstSupplier] = 'Jiangmen Xinpeng';
        $cells[$nameRow][$firstSupplier + 1] = 'Quanzhou Congxi';

        $this->assertTrue($this->service->isSupplierNameRow($cells, $nameRow, $specCol));
        $this->assertSame('Jiangmen Xinpeng', $this->service->supplierNameForColumn($cells, $firstSupplier));
        $this->assertSame(2, $this->service->countNamedSupplierColumns($cells));
        $this->assertTrue($this->service->hasMeaningfulContent($cells));
    }

    public function test_header_supplier_label_is_not_the_name_row(): void
    {
        $cells = [
            ['Amazon', '5 Core', 'Supplier', 'Critical', 'QC', 'Jiangmen Xinpeng Home Products Co., LTD', 'Quanzhou Congxi Furniture Co., LTD'],
            ['', '', 'Comm', '', '', '', ''],
            ['', '', 'Product Pictures', 'Normal', 'Normal', '[cmp-photo:abc.png]', ''],
            ['', '', 'Product price/USD', 'Normal', 'Normal', '16.37', '17.11'],
        ];

        $specCol = $this->service->detectSpecColumnIndex($cells);
        $this->assertSame(2, $specCol);
        $this->assertFalse($this->service->isSupplierNameRow($cells, 0, $specCol));
        $this->assertSame('Jiangmen Xinpeng Home Products Co., LTD', $this->service->supplierNameForColumn($cells, 5));
        $this->assertSame(2, $this->service->countNamedSupplierColumns($cells));
        $this->assertTrue($this->service->hasMeaningfulContent($cells));
        $this->assertGreaterThan(5, $this->service->filledCellScore($cells));
    }

    public function test_product_pictures_label_matches_photo_row(): void
    {
        $cells = [
            ['Amazon', '5 Core', 'Supplier', 'Critical', 'QC', 'Jiangmen'],
            ['', '', 'Product Pictures', '', '', '[cmp-photo:abc.png]'],
        ];

        $specCol = $this->service->detectSpecColumnIndex($cells);
        $this->assertSame(1, $this->service->findRowIndexByLabel($cells, 'product photo', $specCol));
    }

    public function test_blank_template_score_is_far_below_a_filled_sheet(): void
    {
        $blank = $this->service->ensureLeadColumns(ComparisonData::defaultSheetCells());
        $filled = [
            ['Amazon', '5 Core', 'Supplier', 'Critical', 'QC', 'Jiangmen Xinpeng', 'Quanzhou Congxi'],
            ['', '', 'Comm', '', '', '', ''],
            ['', '', 'Product Pictures', 'Normal', 'Normal', '[cmp-photo:a.png]', '[cmp-photo:b.png]'],
            ['', '', 'Product price/USD', 'Normal', 'Normal', '16.37', '17.11'],
        ];

        $this->assertSame(0, $this->service->filledCellScore($blank));
        $this->assertGreaterThan(12, $this->service->filledCellScore($filled));
    }
}
