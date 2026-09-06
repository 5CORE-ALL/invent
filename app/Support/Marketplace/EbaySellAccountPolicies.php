<?php

namespace App\Support\Marketplace;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sell Account business policies (shipping / payment / return) for any eBay store.
 */
class EbaySellAccountPolicies
{
    /**
     * @return array{
     *   success: bool,
     *   shipping: list<array{id: string, name: string}>,
     *   payment: list<array{id: string, name: string}>,
     *   return: list<array{id: string, name: string}>,
     *   message?: string
     * }
     */
    public static function list(string $token, string $marketplaceId = 'EBAY_US'): array
    {
        $empty = ['shipping' => [], 'payment' => [], 'return' => []];
        $token = trim($token);
        if ($token === '') {
            return array_merge(['success' => false, 'message' => 'eBay token missing.'], $empty);
        }

        try {
            $map = [
                'shipping' => "https://api.ebay.com/sell/account/v1/fulfillment_policy?marketplace_id={$marketplaceId}",
                'payment' => "https://api.ebay.com/sell/account/v1/payment_policy?marketplace_id={$marketplaceId}",
                'return' => "https://api.ebay.com/sell/account/v1/return_policy?marketplace_id={$marketplaceId}",
            ];
            $out = $empty;
            foreach ($map as $key => $url) {
                $response = Http::withoutVerifying()
                    ->withToken($token)
                    ->acceptJson()
                    ->timeout(30)
                    ->get($url);
                if ($response->failed()) {
                    Log::warning("eBay {$key} policies failed", [
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 800),
                    ]);
                    continue;
                }
                $json = $response->json() ?? [];
                $listKey = match ($key) {
                    'shipping' => 'fulfillmentPolicies',
                    'payment' => 'paymentPolicies',
                    default => 'returnPolicies',
                };
                $idKey = match ($key) {
                    'shipping' => 'fulfillmentPolicyId',
                    'payment' => 'paymentPolicyId',
                    default => 'returnPolicyId',
                };
                foreach (($json[$listKey] ?? []) as $policy) {
                    $id = trim((string) ($policy[$idKey] ?? ''));
                    $name = trim((string) ($policy['name'] ?? $id));
                    if ($id === '') {
                        continue;
                    }
                    $out[$key][] = ['id' => $id, 'name' => $name];
                }
            }

            return array_merge(['success' => true], $out);
        } catch (\Throwable $e) {
            return array_merge(['success' => false, 'message' => $e->getMessage()], $empty);
        }
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array{shipping: string, payment: string, return: string}
     */
    public static function resolve(string $token, array $defaults): array
    {
        $shipping = trim((string) ($defaults['shipping_policy_id'] ?? ''));
        $payment = trim((string) ($defaults['payment_policy_id'] ?? ''));
        $return = trim((string) ($defaults['return_policy_id'] ?? ''));
        $policies = self::list($token);

        if ($shipping === '') {
            $want = strtolower(trim((string) ($defaults['shipping_policy_name'] ?? 'as per weight')));
            foreach ($policies['shipping'] ?? [] as $policy) {
                if (! is_array($policy)) {
                    continue;
                }
                $id = trim((string) ($policy['id'] ?? ''));
                $name = strtolower(trim((string) ($policy['name'] ?? '')));
                if ($id === '') {
                    continue;
                }
                if ($want !== '' && $name !== '' && str_contains($name, $want)) {
                    $shipping = $id;
                    break;
                }
            }
            if ($shipping === '') {
                $shipping = trim((string) ($policies['shipping'][0]['id'] ?? ''));
            }
        }
        if ($payment === '') {
            $payment = trim((string) ($policies['payment'][0]['id'] ?? ''));
        }
        if ($return === '') {
            $return = trim((string) ($policies['return'][0]['id'] ?? ''));
        }

        return [
            'shipping' => $shipping,
            'payment' => $payment,
            'return' => $return,
        ];
    }

    /**
     * Use a preferred ID only when it exists on this seller account.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $preferred
     * @return array{shipping: string, payment: string, return: string}
     */
    public static function resolveValid(string $token, array $defaults, array $preferred = []): array
    {
        $policies = self::list($token);
        $out = [];
        foreach (['shipping', 'payment', 'return'] as $key) {
            $live = [];
            foreach ($policies[$key] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = trim((string) ($row['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $live[$id] = strtolower(trim((string) ($row['name'] ?? '')));
            }

            $candidates = [
                trim((string) ($preferred[$key] ?? $preferred[$key.'_policy_id'] ?? '')),
                trim((string) ($defaults[$key.'_policy_id'] ?? '')),
            ];
            $picked = '';
            foreach ($candidates as $candidate) {
                if ($candidate !== '' && isset($live[$candidate])) {
                    $picked = $candidate;
                    break;
                }
            }
            if ($picked === '') {
                $want = strtolower(trim((string) ($defaults[$key.'_policy_name'] ?? ($key === 'shipping' ? 'as per weight' : ''))));
                if ($want !== '') {
                    foreach ($live as $id => $name) {
                        if ($name !== '' && str_contains($name, $want)) {
                            $picked = $id;
                            break;
                        }
                    }
                }
            }
            if ($picked === '' && $live !== []) {
                $picked = (string) array_key_first($live);
            }
            $out[$key] = $picked;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultsForChannel(string $channel): array
    {
        $key = ListingChannelCounts::normalize($channel);
        if (in_array($key, ['ebay', 'ebay1', 'ebayone'], true)) {
            return (array) config('listing_manager.ebay1_defaults', []);
        }
        if (in_array($key, ['ebay3', 'ebaythree'], true)) {
            return (array) config('listing_manager.ebay3_defaults', []);
        }

        return (array) config('listing_manager.ebay2_defaults', []);
    }
}
