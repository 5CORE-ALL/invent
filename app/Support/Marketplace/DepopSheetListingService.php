<?php

namespace App\Support\Marketplace;

use Illuminate\Http\UploadedFile;

class DepopSheetListingService
{
    public const SKU_HEADERS = SheetListingCatalogService::SKU_HEADERS;

    public function importUploadedCatalog(UploadedFile $file): array
    {
        return app(SheetListingCatalogService::class)->importUploadedCatalog('depop', $file);
    }

    public function parseCsvFile(string $path): array
    {
        return app(SheetListingCatalogService::class)->parseCsvFile($path);
    }

    public function skuFromRow(array $row): string
    {
        return app(SheetListingCatalogService::class)->skuFromRow($row);
    }
}
