<?php

namespace App\Services\Wms;

use App\Models\ProductMaster;

class BarcodeService
{
    public function generateForProduct(ProductMaster $product): string
    {
        $base = preg_replace('/\s+/', '', (string) $product->sku);
        $base = preg_replace('/[^A-Za-z0-9]/', '', $base) ?: 'SKU';
        $suffix = str_pad((string) $product->id, 6, '0', STR_PAD_LEFT);

        return 'INV'.$suffix.strtoupper(substr($base, 0, 20));
    }

    public function ensureBarcode(ProductMaster $product): string
    {
        if ($product->barcode) {
            return $product->barcode;
        }

        $code = $this->generateForProduct($product);
        while (ProductMaster::withTrashed()->where('barcode', $code)->where('id', '!=', $product->id)->exists()) {
            $code = $code.'X'.random_int(10, 99);
        }

        $product->barcode = $code;
        $product->saveQuietly();

        return $code;
    }
}
