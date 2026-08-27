<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\ListingManagerChannelDraft;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use App\Support\Marketplace\ListingManagerEbayTradingPublisher;
use App\Support\Marketplace\ListingManagerFamily;
use Illuminate\Support\Facades\Schema;

class ListingManagerPublishDispatcher
{
    /**
     * @param  array<string, mixed>  $details
     * @return array{success: bool, message: string, item_id?: string|null, sibling_skus?: list<string>}
     */
    public function publish(ListingManagerChannelDraft $draft, array $details): array
    {
        $channelName = (string) ($draft->channel->channel ?? '');
        $key = ListingChannelCounts::normalize($channelName);
        $sku = trim((string) $draft->seller_sku);

        if (in_array($key, ['ebay', 'ebay1', 'ebayone', 'ebay2', 'ebaytwo', 'ebay3', 'ebaythree'], true)) {
            return $this->publishEbay($key, $draft, $details);
        }
        if (in_array($key, ['temu2', 'temutwo'], true)) {
            return $this->publishTemu2($sku);
        }
        if ($key === 'faire') {
            return $this->publishFaire($sku);
        }
        if (in_array($key, ['reverb', 'reverbcom'], true)) {
            return $this->publishReverb($sku);
        }

        $url = ListingChannelCounts::listingUrl($channelName);
        $hint = $url
            ? ' Draft is ready. Finish publish on the channel listing page: '.$url
            : ' Draft is ready. Open the marketplace listing page to finish publish.';

        return [
            'success' => false,
            'message' => $channelName.' is connected for drafts and variation grouping. Direct API publish is not wired yet.'.$hint,
            'queued' => true,
        ];
    }

    public static function canDirectPublish(string $channelName): bool
    {
        $key = ListingChannelCounts::normalize($channelName);

        return in_array($key, [
            'ebay', 'ebay1', 'ebayone',
            'ebay2', 'ebaytwo',
            'ebay3', 'ebaythree',
            'temu2', 'temutwo',
            'faire',
            'reverb', 'reverbcom',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{success: bool, message: string, item_id?: string|null, sibling_skus?: list<string>}
     */
    private function publishEbay(string $key, ListingManagerChannelDraft $draft, array $details): array
    {
        $variations = $this->variationPayload($draft, $details);
        $result = ListingManagerEbayTradingPublisher::publish($key, array_merge($details, [
            'sku' => $draft->seller_sku,
            'title' => $draft->title,
            'price' => $draft->price,
            'quantity' => $draft->quantity,
            'variations' => $variations,
        ]));

        if (! ($result['success'] ?? false)) {
            return $result;
        }

        $itemId = trim((string) ($result['item_id'] ?? ''));
        $skus = $variations !== []
            ? array_values(array_map(fn ($row) => (string) $row['sku'], $variations))
            : [trim((string) $draft->seller_sku)];
        $this->persistEbayMetrics($key, $skus, $itemId, (string) $draft->title, $draft->price, $draft->quantity);

        return [
            'success' => true,
            'message' => $result['message'] ?? 'Published.',
            'item_id' => $itemId !== '' ? $itemId : null,
            'sibling_skus' => $skus,
        ];
    }

    /**
     * @return array{success: bool, message: string, item_id?: string|null, sibling_skus?: list<string>}
     */
    private function publishTemu2(string $sku): array
    {
        $result = app(Temu2ListingPublishService::class)->publish($sku);
        if (! ($result['success'] ?? false)) {
            return $result;
        }

        return [
            'success' => true,
            'message' => $result['message'] ?? 'Published to Temu 2.',
            'item_id' => $result['goods_id'] ?? null,
            'sibling_skus' => is_array($result['skus'] ?? null) ? $result['skus'] : [$sku],
        ];
    }

    /**
     * @return array{success: bool, message: string, item_id?: string|null, sibling_skus?: list<string>}
     */
    private function publishFaire(string $sku): array
    {
        $result = app(FaireListingPublishService::class)->publishSkus([$sku], true);
        if (! ($result['success'] ?? false)) {
            return $result;
        }

        return [
            'success' => true,
            'message' => $result['message'] ?? 'Published to Faire.',
            'item_id' => $result['goods_id'] ?? null,
            'sibling_skus' => is_array($result['skus'] ?? null) ? $result['skus'] : [$sku],
        ];
    }

    /**
     * @return array{success: bool, message: string, item_id?: string|null, sibling_skus?: list<string>}
     */
    private function publishReverb(string $sku): array
    {
        $result = app(ReverbListingPublishService::class)->publishSkus([$sku], false, 'single');
        if (! ($result['success'] ?? false)) {
            return $result;
        }

        return [
            'success' => true,
            'message' => $result['message'] ?? 'Published to Reverb.',
            'item_id' => $result['goods_id'] ?? null,
            'sibling_skus' => is_array($result['skus'] ?? null) ? $result['skus'] : [$sku],
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return list<array{sku: string, price: float, quantity: int, variation_label: string, upc: string}>
     */
    private function variationPayload(ListingManagerChannelDraft $draft, array $details): array
    {
        $family = ListingManagerFamily::forSku((string) $draft->seller_sku);
        if (count($family['skus']) < 2) {
            return [];
        }

        $rows = [];
        foreach ($family['children'] as $child) {
            $sku = (string) $child['sku'];
            $hydrated = ListingManagerAmazonHydrator::hydrate($sku, false);
            $price = (float) ($hydrated['price'] ?? $draft->price ?? 0);
            if ($sku === (string) $draft->seller_sku && $draft->price) {
                $price = (float) $draft->price;
            }
            $liveQty = ListingManagerAmazonHydrator::shopifyQuantity($sku, true);
            $qty = $liveQty ?? (int) ($hydrated['quantity'] ?? $draft->quantity ?? 0);
            $rows[] = [
                'sku' => $sku,
                'price' => $price,
                'quantity' => $qty,
                'variation_label' => (string) $child['variation_label'],
                'upc' => (string) ($hydrated['upc'] ?? ($details['upc'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $skus
     */
    private function persistEbayMetrics(
        string $key,
        array $skus,
        string $itemId,
        string $title,
        mixed $price,
        mixed $qty
    ): void {
        if ($itemId === '') {
            return;
        }

        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $link = 'https://www.ebay.com/itm/'.$itemId;
            if (in_array($key, ['ebay2', 'ebaytwo'], true) && Schema::hasTable('ebay_2_metrics')) {
                Ebay2Metric::query()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'item_id' => $itemId,
                        'ebay_title' => $title,
                        'ebay_price' => $price,
                        'ebay_stock' => $qty,
                        'ebay_link' => $link,
                    ]
                );
            } elseif (in_array($key, ['ebay3', 'ebaythree'], true) && Schema::hasTable('ebay_3_metrics')) {
                Ebay3Metric::query()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'item_id' => $itemId,
                        'ebay_title' => $title,
                        'ebay_price' => $price,
                        'ebay_stock' => $qty,
                        'ebay_link' => $link,
                    ]
                );
            } elseif (in_array($key, ['ebay', 'ebay1', 'ebayone'], true) && Schema::hasTable('ebay_metrics')) {
                EbayMetric::query()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'item_id' => $itemId,
                        'ebay_title' => $title,
                        'ebay_price' => $price,
                        'ebay_stock' => $qty,
                        'ebay_link' => $link,
                    ]
                );
            }
        }
    }
}
