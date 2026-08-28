<?php

namespace App\Support\Badges;

use App\Models\ProductRawImage;

class RawImagesHero2BadgeCalculator extends RawImagesBadgeCalculator
{
    public const PAGE_NAME = 'raw-images-hero-2';

    protected static function kind(): string
    {
        return ProductRawImage::KIND_HERO_2;
    }
}
