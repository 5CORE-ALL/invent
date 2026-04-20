<?php

namespace App\Services\Marketplaces;

use App\Models\SkuImage;

/**
 * Placeholder: SKU Image Manager resolves services by marketplace code (e.g. ebay).
 * Reverb has a real implementation; eBay image push is not wired here yet.
 */
class EbayService implements MarketplaceInterface
{
    public function uploadImage(SkuImage $image): array
    {
        return [
            'success' => false,
            'data' => ['file' => $image->file_name],
            'message' => 'eBay image push is not implemented in this app yet. Use Reverb, or extend EbayService with the eBay media/listing API.',
        ];
    }
}
