<?php

namespace App\Support\Marketplace;

use App\Models\AmazonOrder;

/**
 * Shared cancel / refund / closed detection for Sales Order Fulfillment Pending.
 */
final class SofOrderCancelDetector
{
    /**
     * @var list<string>
     */
    private const CANCEL_STATUSES = [
        'CANCELED',
        'CANCELLED',
        'CANCEL_REQUESTED',
        'CANCELLATION_REQUESTED',
        'VOID',
        'VOIDED',
        'INVALID',
        'IN_CANCEL',
        'ORDER_CANCEL',
        'CLOSED',
        'CLOSE',
    ];

    /**
     * @var list<string>
     */
    private const REFUND_STATUSES = [
        'REFUND_OK',
        'WAIT_REFUND',
        'IN_REFUND',
        'REFUND_SUCCESS',
        'FULLY_REFUNDED',
        'REFUNDED',
    ];

    public static function statusLooksCancelled(string $raw): bool
    {
        $u = self::norm($raw);
        if ($u === '') {
            return false;
        }
        if (in_array($u, self::CANCEL_STATUSES, true)) {
            return true;
        }
        if (str_contains($u, 'CANCEL')) {
            return true;
        }
        if (in_array($u, self::REFUND_STATUSES, true)) {
            return true;
        }
        if (str_contains($u, 'REFUND') && ! str_contains($u, 'NO_REFUND') && $u !== 'REFUND_CLOSE') {
            return true;
        }

        return str_contains($u, 'CLOSED');
    }

    public static function payloadLooksCancelled(mixed $payload): bool
    {
        $payload = self::unwrapPayload($payload);
        if ($payload === []) {
            return false;
        }

        foreach ([
            'order_status',
            'orderStatus',
            'OrderStatus',
            'status',
            'orderFulfillmentStatus',
            'frozen_status',
            'frozenStatus',
            'issue_status',
            'end_reason',
            'order_end_reason',
            'close_reason',
            'refund_status',
            'refundStatus',
        ] as $key) {
            if (isset($payload[$key]) && self::statusLooksCancelled((string) $payload[$key])) {
                return true;
            }
        }

        $cancelStatus = is_array($payload['cancelStatus'] ?? null) ? $payload['cancelStatus'] : [];
        $cancelStatusAlt = is_array($payload['cancel_status'] ?? null) ? $payload['cancel_status'] : [];
        $cancelState = $cancelStatus['cancelState']
            ?? $cancelStatusAlt['cancel_state']
            ?? $payload['cancelState']
            ?? $payload['cancel_state']
            ?? '';
        if (self::statusLooksCancelled((string) $cancelState)) {
            return true;
        }

        $paymentSummary = is_array($payload['paymentSummary'] ?? null) ? $payload['paymentSummary'] : [];
        $payment = (string) (
            $payload['orderPaymentStatus']
            ?? $paymentSummary['paymentStatus']
            ?? $payload['payment_status']
            ?? ''
        );
        $payU = self::norm($payment);
        if (str_contains($payU, 'FULLY_REFUNDED') || $payU === 'REFUNDED') {
            return true;
        }

        $refundInfo = is_array($payload['refund_info'] ?? null) ? $payload['refund_info'] : [];
        foreach (['refund_status', 'refundStatus', 'status'] as $key) {
            if (isset($refundInfo[$key]) && self::statusLooksCancelled((string) $refundInfo[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function unwrapPayload(mixed $payload): array
    {
        $payload = AmazonOrder::decodeRawPayload($payload);
        if ($payload === []) {
            return [];
        }
        if (isset($payload['order']) && is_array($payload['order'])) {
            $payload = array_merge($payload, $payload['order']);
        }

        return $payload;
    }

    private static function norm(string $raw): string
    {
        return strtoupper(str_replace(['-', ' '], '_', trim($raw)));
    }
}
