<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;

/**
 * Shared paid-order checks for Marketplace Manager auto-import / Push to Shopify.
 */
class MarketplaceOrderPaidFilter
{
    public static function unpaidPushBlockedMessage(): string
    {
        return 'This order is not paid. Turn off “Only auto-import paid orders” in Settings if you want to push unpaid orders to Shopify.';
    }

    /**
     * When paid-only is on, unpaid orders cannot be pushed (auto or manual).
     */
    public static function blocksUnpaidPush(string $marketplace, object $order): bool
    {
        return MarketplaceSyncSettings::importPaidOrdersOnly($marketplace)
            && ! self::isPaid($marketplace, $order);
    }

    /**
     * AliExpress / Alibaba statuses that mean the buyer has not paid yet.
     *
     * @var list<string>
     */
    private const AE_UNPAID = [
        'PLACE_ORDER_SUCCESS',
        'WAIT_BUYER_PAY',
        'PAYMENT_PENDING',
        'UNPAID',
        'NOT_PAY',
        'PENDING_PAYMENT',
    ];

    /**
     * @param  object{
     *   status?: mixed,
     *   order_paid_at?: mixed,
     *   raw_payload?: mixed
     * }  $order
     */
    public static function isPaid(string $marketplace, object $order): bool
    {
        $marketplace = strtolower(trim($marketplace));

        return match ($marketplace) {
            'newegg' => self::isNeweggPaid($order),
            'shein' => self::isSheinPaid($order),
            'topdawg' => self::isTopDawgPaid($order),
            'temu', 'temu2' => self::isTemuPaid($order),
            'purchasingpower' => self::isPurchasingPowerPaid($order),
            'wayfair' => self::isWayfairPaid($order),
            'bestbuy' => self::isBestBuyPaid($order),
            'macy' => self::isMacyPaid($order),
            'doba' => self::isDobaPaid($order),
            'ebay1', 'ebay2', 'ebay3' => self::isEbayPaid($order),
            'faire' => self::isFairePaid($order),
            'tiktok2' => self::isTikTok2Paid($order),
            'amazon' => self::isAmazonPaid($order),
            'reverb' => self::isReverbPaid($order),
            'aliexpress', 'alibaba' => self::isAliFamilyPaid($order),
            default => true,
        };
    }

    protected static function isTopDawgPaid(object $order): bool
    {
        $status = strtoupper(trim((string) ($order->status ?? '')));
        if ($status === '' || str_contains($status, 'CANCEL')) {
            return ! str_contains($status, 'CANCEL');
        }

        // Paid when we have a paid timestamp or a non-pending status.
        if (! empty($order->order_paid_at)) {
            return true;
        }

        return ! in_array($status, ['UNPAID', 'PENDING', 'PAYMENT_PENDING', 'PENDING_PAYMENT'], true);
    }

    protected static function isTemuPaid(object $order): bool
    {
        $parent = strtoupper(trim((string) ($order->parent_order_status_text ?? '')));
        $line = strtoupper(trim((string) ($order->order_status_text ?? '')));
        $status = $parent !== '' ? $parent : $line;

        if ($status === '' || str_contains($status, 'CANCEL')) {
            return ! str_contains($status, 'CANCEL');
        }

        $paidish = ['UN_SHIPPING', 'SHIPPED', 'DELIVERED', 'RECEIVED', 'COMPLETED', 'PARTIAL_SHIPPED'];
        foreach ($paidish as $needle) {
            if (str_contains($status, $needle)) {
                return true;
            }
        }

        return ! in_array($status, ['UNPAID', 'PENDING', 'PENDING_PAYMENT', 'WAIT_PAY', 'NOT_PAY'], true);
    }

    protected static function isPurchasingPowerPaid(object $order): bool
    {
        $status = strtoupper(trim((string) ($order->status ?? '')));
        if ($status === '' || str_contains($status, 'CANCEL')) {
            return ! str_contains($status, 'CANCEL');
        }

        $paidish = ['SHIPPED', 'SHIPPING', 'RECEIVED', 'CLOSED', 'COMPLETED', 'TO_COLLECT'];
        foreach ($paidish as $needle) {
            if (str_contains($status, $needle)) {
                return true;
            }
        }

        return ! in_array($status, ['UNPAID', 'PENDING', 'PAYMENT_PENDING', 'WAITING_ACCEPTANCE', 'STAGING'], true);
    }

    protected static function isWayfairPaid(object $order): bool
    {
        $status = strtoupper(trim((string) ($order->status ?? '')));
        if ($status === '' || str_contains($status, 'CANCEL')) {
            return ! str_contains($status, 'CANCEL');
        }

        return ! in_array($status, ['UNPAID', 'PENDING', 'PAYMENT_PENDING'], true);
    }

    protected static function isBestBuyPaid(object $order): bool
    {
        return self::isPurchasingPowerPaid($order);
    }

    protected static function isMacyPaid(object $order): bool
    {
        return self::isPurchasingPowerPaid($order);
    }

    protected static function isDobaPaid(object $order): bool
    {
        if (! empty($order->pay_time)) {
            return true;
        }

        $status = strtoupper(trim((string) ($order->order_status ?? '')));
        if ($status === '' || str_contains($status, 'CANCEL')) {
            return ! str_contains($status, 'CANCEL');
        }

        $paidish = ['PAID', 'SHIPPED', 'SHIPPING', 'DELIVERED', 'COMPLETED', 'CONFIRMED', 'PROCESSING'];
        foreach ($paidish as $needle) {
            if (str_contains($status, $needle)) {
                return true;
            }
        }

        return ! in_array($status, ['UNPAID', 'PENDING', 'PAYMENT_PENDING', 'WAIT_PAY', 'NOT_PAY'], true);
    }

    protected static function isEbayPaid(object $order): bool
    {
        $status = strtoupper(trim((string) ($order->status ?? '')));
        $raw = is_array($order->raw_payload ?? null) ? $order->raw_payload : [];
        $payment = strtoupper(trim((string) ($raw['orderPaymentStatus'] ?? '')));

        if (str_contains($status, 'CANCEL') || str_contains($payment, 'FAILED') || str_contains($payment, 'PENDING')) {
            // PENDING payment = not paid; FAILED/CANCEL = not importable.
            if (str_contains($payment, 'PENDING') || str_contains($payment, 'FAILED') || str_contains($status, 'CANCEL')) {
                return false;
            }
        }

        if (in_array($payment, ['PAID', 'FULLY_REFUNDED', 'PARTIALLY_REFUNDED', ''], true)
            || $payment === ''
            || str_contains($payment, 'PAID')) {
            // FULLY_REFUNDED should not import.
            if (str_contains($payment, 'FULLY_REFUNDED')) {
                return false;
            }

            return true;
        }

        return ! in_array($status, ['UNPAID', 'PAYMENT_PENDING', 'PENDING_PAYMENT'], true);
    }

    /** @deprecated Use isEbayPaid() */
    protected static function isEbay3Paid(object $order): bool
    {
        return self::isEbayPaid($order);
    }

    protected static function isSheinPaid(object $order): bool
    {
        $status = strtoupper(trim((string) ($order->status ?? '')));
        $raw = is_array($order->raw_payload ?? null) ? $order->raw_payload : [];
        $code = (int) ($raw['orderStatus'] ?? 0);

        // Shein: 1 = Pending (unpaid/processing), 6 = Refund — treat unpaid/refund as not paid.
        // 2+ (To Be Shipped / Shipped / Received) = paid/importable.
        if ($code === 1 || str_contains($status, 'PENDING')) {
            // Pending can mean awaiting seller action after pay — treat as paid for import.
            return true;
        }
        if ($code === 6 || str_contains($status, 'REFUND')) {
            return false;
        }
        if (in_array($status, ['UNPAID', 'PAYMENT_PENDING', 'NOT_PAY', 'PENDING_PAYMENT'], true)) {
            return false;
        }

        // processing / paid / to be shipped / shipped / received → paid
        return true;
    }

    protected static function isFairePaid(object $order): bool
    {
        $status = strtoupper(trim((string) ($order->status ?? '')));
        $raw = is_array($order->raw_payload ?? null) ? $order->raw_payload : [];
        if (is_array($raw['order'] ?? null)) {
            $raw = $raw['order'];
        }
        $state = strtoupper(trim((string) ($raw['state'] ?? $raw['status'] ?? $status)));

        // Faire unpaid / canceled-like states — treat everything else as importable.
        $unpaid = ['NEW', 'CANCELED', 'CANCELLED', 'DRAFT', 'PENDING_RETAILER_CONFIRMATION'];
        if ($state !== '' && in_array($state, $unpaid, true)) {
            return false;
        }

        return true;
    }

    protected static function isTikTok2Paid(object $order): bool
    {
        $status = strtoupper(trim((string) ($order->order_status ?? '')));

        $unpaid = ['UNPAID', 'ON_HOLD', 'CANCELLED', 'CANCELED'];
        if ($status !== '' && in_array($status, $unpaid, true)) {
            return false;
        }

        return true;
    }

    protected static function isNeweggPaid(object $order): bool
    {
        $status = strtoupper(trim((string) ($order->status ?? '')));
        $raw = is_array($order->raw_payload ?? null) ? $order->raw_payload : [];
        $code = (string) ($raw['OrderStatus'] ?? $raw['OrderStatusCode'] ?? '');
        $desc = strtoupper(trim((string) ($raw['OrderStatusDescription'] ?? $status)));

        // Newegg: 5 = Payment Pending, 4 = Voided
        if ($status === '5' || $code === '5' || str_contains($desc, 'PAYMENT PENDING')) {
            return false;
        }
        if ($status === '4' || $code === '4' || str_contains($desc, 'VOID')) {
            return false;
        }

        return true;
    }

    protected static function isReverbPaid(object $order): bool
    {
        if (! empty($order->order_paid_at)) {
            return true;
        }

        $status = strtolower(trim((string) ($order->status ?? '')));
        if ($status === '') {
            return false;
        }

        if (in_array($status, ['unpaid', 'payment_pending', 'pending_payment', 'awaiting_payment'], true)) {
            return false;
        }

        // Reverb often uses paid / shipped / received once money has cleared.
        return in_array($status, [
            'paid',
            'shipped',
            'received',
            'picked_up',
            'completed',
            'partially_refunded',
            'refunded',
        ], true) || str_contains($status, 'paid');
    }

    protected static function isAliFamilyPaid(object $order): bool
    {
        $status = strtoupper(trim((string) ($order->status ?? '')));
        if ($status === '') {
            return false;
        }

        foreach (self::AE_UNPAID as $unpaid) {
            if ($status === $unpaid || str_contains($status, $unpaid)) {
                return false;
            }
        }

        // Anything past buyer-pay is treated as paid for auto-import.
        return true;
    }

    protected static function isAmazonPaid(object $order): bool
    {
        $status = trim((string) ($order->status ?? ''));

        return $status === '' || ! in_array($status, ['Canceled', 'Cancelled', 'Pending'], true);
    }
}
