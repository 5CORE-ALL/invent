<?php

namespace App\Services\Marketplaces;

use App\Models\SkuImage;

interface MarketplaceInterface
{
    /**
     * @return array{success: bool, data: array<string, mixed>, message: string}
     */
    public function uploadImage(SkuImage $image): array;
}
