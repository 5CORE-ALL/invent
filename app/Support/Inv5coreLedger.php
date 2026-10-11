<?php

namespace App\Support;

/**
 * Inventory rules for INV Management 5Core.
 *
 * INV / L30 / DIL% stay on the CP Master tables (product_master + shopify_skus).
 * INV APP is a separate ledger: Shopify on-hand is copied once per SKU, then
 * this page never reads Shopify again for that SKU. Later app sales and
 * manual adjustments are the only movements.
 */
class Inv5coreLedger
{
    /** @var list<string> */
    public const SKIPPED_STATUSES = ['refunded', 'voided', 'cancelled', 'canceled', 'void'];

    public static function isParentSku(?string $sku): bool
    {
        return str_contains(strtoupper(trim((string) $sku)), 'PARENT');
    }

    public static function statusSkipsSale(?string $status): bool
    {
        $status = strtolower(trim((string) $status));
        if ($status === '') {
            return false;
        }

        return in_array($status, self::SKIPPED_STATUSES, true);
    }

    /**
     * Shipped / delivered marketplace statuses. Pending orders are committed
     * only; on hand drops when the order is fulfilled.
     */
    public static function statusIsFulfilled(?string $status): bool
    {
        $compact = str_replace([' ', '_', '-'], '', strtolower(trim((string) $status)));

        return in_array($compact, [
            'shipped',
            'partiallyshipped',
            'fulfilled',
            'delivered',
            'partiallydelivered',
            'completed',
            'complete',
            'intransit',
            'pickedup',
            'closed',
            'received',
            'awaitingcollection',
            'partiallyshipping',
            'buyeracceptgoods',
            'finish',
            'tradefinished',
            'waitbuyeracceptgoods',
        ], true);
    }

    /**
     * @return array{on_hand: float, committed: float, unavailable: float, available: float}
     */
    public static function nextStates(
        float $onHand,
        float $committed,
        float $unavailable,
        float $onHandDelta,
        float $committedDelta = 0.0,
        float $unavailableDelta = 0.0
    ): array {
        $onHand = self::roundQty($onHand + $onHandDelta);
        $committed = self::roundQty($committed + $committedDelta);
        $unavailable = self::roundQty($unavailable + $unavailableDelta);

        return [
            'on_hand' => $onHand,
            'committed' => $committed,
            'unavailable' => $unavailable,
            'available' => self::roundQty($onHand - $committed - $unavailable),
        ];
    }

    public static function historyActivity(string $txnType, string $reference): string
    {
        $ref = ltrim(trim($reference), '#');
        $suffix = $ref !== '' ? ' (#'.$ref.')' : '';

        return match ($txnType) {
            'order_created' => 'Order created'.$suffix,
            'order_fulfilled', 'sale' => 'Order fulfilled'.$suffix,
            'return' => 'Order canceled'.$suffix,
            'opening' => 'Opening inventory',
            'incoming' => 'Inventory received',
            'write_off' => 'Write-off',
            'adjustment' => 'Inventory adjusted',
            default => ucfirst(str_replace('_', ' ', $txnType)).$suffix,
        };
    }

    /**
     * Order and opening rows are labeled with the marketplace stored on the
     * movement. A person's name is kept for manual adjustments.
     */
    public static function historyCreatedBy(string $txnType, string $channel, string $userName): string
    {
        $channel = trim($channel);
        $userName = trim($userName);
        $fromMarketplace = in_array($txnType, ['opening', 'order_created', 'order_fulfilled', 'sale', 'return'], true);
        if ($fromMarketplace && $channel !== '' && strcasecmp($channel, 'App') !== 0) {
            return $channel;
        }
        if ($userName !== '') {
            return $userName;
        }
        if ($channel !== '' && strcasecmp($channel, 'App') !== 0) {
            return $channel;
        }

        return '5Core Inventory';
    }

    /** @var list<string> */
    private const SHOPIFY_NATIVE_SOURCES = [
        'web',
        'pos',
        'shop',
        'shopify',
        'shopify_draft_order',
        'online_store',
        'iphone',
        'android',
        'hydrogen',
        'checkout-via-buy-button',
        'checkout-via-buy-now-button',
        'google',
    ];

    /**
     * Longer names first so "temu2" is not labeled Temu.
     *
     * @var array<string, string>
     */
    private const MARKETPLACE_CHANNELS = [
        '145019994113' => 'Doba',
        '179763773441' => "Macy's",
        '189863297025' => 'Newegg',
        'temu 2' => 'Temu 2',
        'temu2' => 'Temu 2',
        'temu 3' => 'Temu 3',
        'temu3' => 'Temu 3',
        'tiktok 2' => 'TikTok 2',
        'tiktok2' => 'TikTok 2',
        'best buy' => 'Best Buy',
        'bestbuy' => 'Best Buy',
        'purchasing power' => 'Purchasing Power',
        'purchasingpower' => 'Purchasing Power',
        'aliexpress' => 'AliExpress',
        'ali express' => 'AliExpress',
        'alibaba' => 'Alibaba',
        'wayfair' => 'Wayfair',
        'newegg' => 'Newegg',
        'topdawg' => 'TopDawg',
        'reverb' => 'Reverb',
        'faire' => 'Faire',
        'shein' => 'Shein',
        'tiktok' => 'TikTok',
        'temu' => 'Temu',
        'ebay' => 'eBay',
        'amazon' => 'Amazon',
        'doba' => 'Doba',
        'macy' => "Macy's",
        'walmart' => 'Walmart',
        'mercari' => 'Mercari',
    ];

    /**
     * A copied Shopify order is labeled with the marketplace it came from.
     * Storefront orders stay Shopify.
     */
    public static function shopifyOrderChannel(string $sourceName, string $tags = ''): ?string
    {
        $source = strtolower(trim($sourceName));
        $blob = trim($source.' '.strtolower($tags));
        foreach (self::MARKETPLACE_CHANNELS as $token => $label) {
            if ($blob !== '' && str_contains($blob, $token)) {
                return $label;
            }
        }
        if ($source === '' || is_numeric($source)) {
            return null;
        }
        if (str_contains($source, 'shopify') || in_array($source, self::SHOPIFY_NATIVE_SOURCES, true)) {
            return 'Shopify';
        }

        return ucwords(str_replace(['_', '-'], ' ', $source));
    }

    /**
     * @return list<array{at: int, stage: int, txn_type: string, reference: string, channel: string, user_name: string, on_hand_delta: float, committed_delta: float, unavailable_delta: float, available_delta: float, in_balance: bool}>
     */
    public static function orderMovementEvents(float $qty, ?string $status, string $reference, string $channel, int $createdAt, ?int $fulfilledAt): array
    {
        $qty = self::roundQty($qty);
        $channel = trim($channel);
        if ($qty <= 0 || $channel === '') {
            return [];
        }
        $fulfilledAt = ($fulfilledAt !== null && $fulfilledAt > $createdAt) ? $fulfilledAt : $createdAt;
        $create = self::historyEvent($createdAt, 1, 'order_created', $reference, $channel, 0.0, $qty, self::deltaForSubtract($qty));
        if (self::statusSkipsSale($status)) {
            return [
                $create,
                self::historyEvent($fulfilledAt, 3, 'return', $reference, $channel, 0.0, self::deltaForSubtract($qty), $qty),
            ];
        }
        if (! self::statusIsFulfilled($status)) {
            return [$create];
        }

        return [
            $create,
            self::historyEvent($fulfilledAt, 2, 'order_fulfilled', $reference, $channel, self::deltaForSubtract($qty), self::deltaForSubtract($qty), $qty),
        ];
    }

    /**
     * @param  list<array{at: int, stage: int, txn_type: string, reference: string, channel: string, user_name: string, on_hand_delta: float, committed_delta: float, unavailable_delta: float, available_delta: float, in_balance: bool}>  $events
     * @return list<array{at: int, txn_type: string, reference: string, channel: string, user_name: string, committed_delta: float, committed_after: float, available_delta: float, available_after: float, on_hand_delta: float, on_hand_after: float}>
     */
    public static function replayHistory(float $endOnHand, float $endCommitted, float $endUnavailable, array $events): array
    {
        usort($events, function (array $a, array $b): int {
            $byTime = $a['at'] <=> $b['at'];

            return $byTime !== 0 ? $byTime : ($a['stage'] <=> $b['stage']);
        });
        $onHandDelta = 0.0;
        $committedDelta = 0.0;
        $unavailableDelta = 0.0;
        foreach ($events as $event) {
            $onHandDelta += (float) $event['on_hand_delta'];
            $committedDelta += (float) $event['committed_delta'];
            $unavailableDelta += (float) $event['unavailable_delta'];
        }
        $onHand = self::roundQty($endOnHand - $onHandDelta);
        $committed = self::roundQty($endCommitted - $committedDelta);
        $unavailable = self::roundQty($endUnavailable - $unavailableDelta);
        $rows = [];
        foreach ($events as $event) {
            $states = self::nextStates(
                $onHand,
                $committed,
                $unavailable,
                (float) $event['on_hand_delta'],
                (float) $event['committed_delta'],
                (float) $event['unavailable_delta']
            );
            $rows[] = [
                'at' => (int) $event['at'],
                'txn_type' => (string) $event['txn_type'],
                'reference' => (string) $event['reference'],
                'channel' => (string) $event['channel'],
                'user_name' => (string) ($event['user_name'] ?? ''),
                'committed_delta' => (float) $event['committed_delta'],
                'committed_after' => $states['committed'],
                'available_delta' => (float) $event['available_delta'],
                'available_after' => $states['available'],
                'on_hand_delta' => (float) $event['on_hand_delta'],
                'on_hand_after' => $states['on_hand'],
            ];
            $onHand = $states['on_hand'];
            $committed = $states['committed'];
            $unavailable = $states['unavailable'];
        }

        return array_reverse($rows);
    }

    /**
     * @return array{at: int, stage: int, txn_type: string, reference: string, channel: string, user_name: string, on_hand_delta: float, committed_delta: float, unavailable_delta: float, available_delta: float, in_balance: bool}
     */
    private static function historyEvent(int $at, int $stage, string $txnType, string $reference, string $channel, float $onHandDelta, float $committedDelta, float $availableDelta): array
    {
        return [
            'at' => $at,
            'stage' => $stage,
            'txn_type' => $txnType,
            'reference' => $reference,
            'channel' => $channel,
            'user_name' => '',
            'on_hand_delta' => $onHandDelta,
            'committed_delta' => $committedDelta,
            'unavailable_delta' => 0.0,
            'available_delta' => $availableDelta,
            'in_balance' => false,
        ];
    }

    /**
     * A source row reduces on-hand only when it was inserted after the
     * watermark saved with the one-time opening. Older rows are already
     * inside that Shopify on-hand number.
     */
    public static function saleIdAffectsBalance(int $sourceId, ?int $watermarkId): bool
    {
        if ($watermarkId === null || $sourceId <= 0) {
            return false;
        }

        return $sourceId > $watermarkId;
    }

    public static function needsReversal(bool $alreadyPosted, bool $statusSkipsSale): bool
    {
        return $alreadyPosted && $statusSkipsSale;
    }

    public static function roundQty(float $qty): float
    {
        return round($qty, 2);
    }

    public static function deltaForSet(float $onHand, float $newQty): float
    {
        return self::roundQty($newQty - $onHand);
    }

    public static function deltaForAdd(float $qty): float
    {
        return self::roundQty(abs($qty));
    }

    public static function deltaForSubtract(float $qty): float
    {
        return self::roundQty(-abs($qty));
    }

    public static function applyDelta(float $onHand, float $delta): float
    {
        return self::roundQty($onHand + $delta);
    }

    /**
     * Same image priority as CP Master: a stored upload, then the Shopify image.
     */
    public static function imagePath(?string $localImage, ?string $shopifyImage): ?string
    {
        if ($localImage && (str_contains($localImage, 'storage/') || str_contains($localImage, '/storage/'))) {
            return '/'.ltrim($localImage, '/');
        }
        if ($shopifyImage) {
            return $shopifyImage;
        }
        if ($localImage) {
            return '/'.ltrim($localImage, '/');
        }

        return null;
    }
}
