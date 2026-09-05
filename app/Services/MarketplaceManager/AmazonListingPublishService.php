<?php

namespace App\Services\MarketplaceManager;

use App\Services\AmazonSpApiService;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use App\Support\Marketplace\ListingManagerPublishStatus;

/**
 * Create/update an Amazon listing via SP-API Listings Items (title, stock, images).
 */
class AmazonListingPublishService
{
    public function __construct(private AmazonSpApiService $api)
    {
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{success: bool, message: string, goods_id?: string|null, skus?: list<string>}
     */
    public function publishSku(string $sku, array $details = [], ?string $title = null, ?int $quantity = null): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU is required.'];
        }
        if (! $this->api->isConfigured()) {
            return ['success' => false, 'message' => 'Amazon SP-API is not connected. Set Amazon client id, secret, refresh token, and seller id.'];
        }

        $title = trim((string) ($title ?? $details['title'] ?? ''));
        $qty = $quantity;
        if ($qty === null && array_key_exists('quantity', $details) && $details['quantity'] !== null && $details['quantity'] !== '') {
            $qty = (int) $details['quantity'];
        }
        $images = [];
        foreach (ListingManagerAmazonHydrator::publishImageUrls($sku, null, 9) as $url) {
            $url = trim((string) $url);
            if ($url !== '' && preg_match('#^https://#i', $url)) {
                $images[] = $url;
            }
        }
        if ($images === [] && is_array($details['images'] ?? null)) {
            foreach ($details['images'] as $url) {
                $url = trim((string) $url);
                if ($url !== '' && preg_match('#^https://#i', $url) && ! in_array($url, $images, true)) {
                    $images[] = $url;
                }
            }
        }

        $asin = ListingManagerPublishStatus::amazonAsinForSku($sku);
        $ok = [];
        $fail = [];

        if ($title !== '') {
            $res = $this->api->updateTitle($sku, $title);
            if ($res['success'] ?? false) {
                $ok[] = 'title';
            } else {
                $fail[] = 'title: '.($res['message'] ?? 'update failed');
            }
        }

        if ($qty !== null) {
            $res = $this->api->updateInventoryBySku($sku, max(0, $qty));
            if ($res['success'] ?? false) {
                $ok[] = 'quantity';
            } else {
                $fail[] = 'quantity: '.($res['message'] ?? 'update failed');
            }
        }

        if ($images !== []) {
            $res = $this->api->updateListingImages($sku, $images);
            if ($res['success'] ?? false) {
                $ok[] = 'images';
            } else {
                $fail[] = 'images: '.($res['message'] ?? 'update failed');
            }
        }

        if ($ok === [] && $fail === []) {
            return [
                'success' => false,
                'message' => 'Nothing to send to Amazon. Add a title, quantity, or Image Master photos first.',
            ];
        }

        $notFound = $fail !== [] && collect($fail)->contains(fn ($m) => stripos((string) $m, 'not found') !== false);
        if ($ok === [] && $notFound) {
            return [
                'success' => false,
                'message' => 'Amazon has no listing for '.$sku.' yet. Create the offer in Seller Central (or match an ASIN), then Save & Publish to update it.',
                'skus' => [$sku],
            ];
        }

        if ($ok === []) {
            return [
                'success' => false,
                'message' => 'Amazon listing update failed. '.implode(' ', $fail),
                'skus' => [$sku],
            ];
        }

        $action = $asin ? 'Updated' : 'Pushed';

        return [
            'success' => true,
            'message' => $action.' Amazon listing for '.$sku.' ('.implode(', ', $ok).').'
                .($fail !== [] ? ' '.implode(' ', $fail) : ''),
            'goods_id' => $asin,
            'skus' => [$sku],
        ];
    }
}
