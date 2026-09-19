<?php

namespace Tests\Unit;

use App\Support\Marketplace\DepopSheetListingService;
use App\Support\Marketplace\ListingChannelCounts;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DepopSheetListingServiceTest extends TestCase
{
    #[Test]
    public function it_treats_depop_as_csv_catalog_on_missing_listing(): void
    {
        $this->assertTrue(ListingChannelCounts::hasListingSource('Depop'));
        $this->assertSame('CSV', ListingChannelCounts::dataSource('Depop'));
        $this->assertTrue(ListingChannelCounts::isCsvCatalogSource('depop'));
        $this->assertTrue(ListingChannelCounts::showsComputedCounts('depop'));
        $this->assertSame(url('/listing-depop'), ListingChannelCounts::listingUrl('Depop'));
    }

    #[Test]
    public function it_reads_sku_from_common_sheet_headers(): void
    {
        $service = new DepopSheetListingService();
        $path = tempnam(sys_get_temp_dir(), 'depop');
        file_put_contents($path, "Seller SKU,URL\nABC-100,https://www.depop.com/products/1\nLS 100-6 RED,\n");

        $rows = $service->parseCsvFile($path);
        @unlink($path);

        $this->assertCount(2, $rows);
        $this->assertSame('ABC-100', $service->skuFromRow($rows[0]));
        $this->assertSame('LS 100-6 RED', $service->skuFromRow($rows[1]));
    }

    #[Test]
    public function it_reads_first_column_when_there_is_no_sku_header(): void
    {
        $service = new DepopSheetListingService();
        $path = tempnam(sys_get_temp_dir(), 'depop');
        file_put_contents($path, "ABC-100\nDEF-200\n");

        $rows = $service->parseCsvFile($path);
        @unlink($path);

        $this->assertSame('ABC-100', $service->skuFromRow($rows[0]));
        $this->assertSame('DEF-200', $service->skuFromRow($rows[1]));
    }
}
