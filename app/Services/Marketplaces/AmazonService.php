<?php

namespace App\Services\Marketplaces;

use App\Models\SkuImage;

class AmazonService implements MarketplaceInterface
{
    public function uploadImage(SkuImage $image): array
    {
        return [
            'success' => true,
            'data' => [
                'status' => 'simulated',
                'file' => $image->file_name,
            ],
            'message' => 'Amazon image upload (simulated).',
        ];
    }
}
