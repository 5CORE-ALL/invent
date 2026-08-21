<?php

namespace App\Support\Badges;

use App\Models\ProductRawImage;

class RawImagesBatchCooBadgeCalculator extends RawImagesBadgeCalculator
{
    public const PAGE_NAME = 'raw-images-batch-coo';

    protected static function kind(): string
    {
        return ProductRawImage::KIND_BATCH_COO;
    }
}
