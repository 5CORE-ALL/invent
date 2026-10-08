<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Only one process may create a Shopify order for a given marketplace order.
 *
 * Jobs, the inline sync loops, the 15-minute fallback and the Push buttons all reached
 * "search Shopify → not found → create" for the same order within seconds of each other.
 * Shopify search does not show a just-created order yet, so each of them created one
 * (Amazon 113-7876038-6872205 → #348416 and #348417). The unique (channel, ref) row is
 * taken before the search and holds the Shopify id once created.
 */
final class ShopifyOrderCreateClaim
{
    public const TABLE = 'marketplace_shopify_order_claims';

    /** A claim with no Shopify id older than this is a crashed or unanswered create; the next caller re-searches Shopify. */
    public const STALE_MINUTES = 10;

    /**
     * @param  list<string>  $refs
     * @return array{state: 'claimed'|'exists'|'busy', shopify_order_id: ?string, taken_over: bool}
     */
    public static function claim(string $channel, array $refs): array
    {
        $channel = self::channelKey($channel);
        $refs = self::normalizeRefs($refs);
        if ($channel === '' || $refs === []) {
            return ['state' => 'claimed', 'shopify_order_id' => null, 'taken_over' => false];
        }

        if (! self::tableReady()) {
            return Cache::add('mm_shopify_claim:'.$channel.':'.$refs[0], 1, now()->addMinutes(self::STALE_MINUTES))
                ? ['state' => 'claimed', 'shopify_order_id' => null, 'taken_over' => false]
                : ['state' => 'busy', 'shopify_order_id' => null, 'taken_over' => false];
        }

        $rows = DB::table(self::TABLE)->where('channel', $channel)->whereIn('ref', $refs)->get();
        foreach ($rows as $row) {
            $id = trim((string) ($row->shopify_order_id ?? ''));
            if ($id !== '') {
                return ['state' => 'exists', 'shopify_order_id' => $id, 'taken_over' => false];
            }
        }

        $staleBefore = now()->subMinutes(self::STALE_MINUTES);
        $takenOver = false;
        foreach ($rows as $row) {
            if ($row->claimed_at !== null && $row->claimed_at > $staleBefore->toDateTimeString()) {
                return ['state' => 'busy', 'shopify_order_id' => null, 'taken_over' => false];
            }
            $takenOver = true;
        }
        if ($takenOver) {
            DB::table(self::TABLE)
                ->where('channel', $channel)
                ->whereIn('ref', $refs)
                ->whereNull('shopify_order_id')
                ->where(function ($q) use ($staleBefore) {
                    $q->whereNull('claimed_at')->orWhere('claimed_at', '<=', $staleBefore);
                })
                ->delete();
        }

        $now = now();
        try {
            DB::transaction(function () use ($channel, $refs, $now) {
                foreach ($refs as $ref) {
                    DB::table(self::TABLE)->insert([
                        'channel' => $channel,
                        'ref' => $ref,
                        'shopify_order_id' => null,
                        'claimed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
        } catch (QueryException $e) {
            if (self::isUniqueViolation($e)) {
                $id = DB::table(self::TABLE)->where('channel', $channel)->whereIn('ref', $refs)
                    ->whereNotNull('shopify_order_id')->value('shopify_order_id');

                return $id
                    ? ['state' => 'exists', 'shopify_order_id' => (string) $id, 'taken_over' => false]
                    : ['state' => 'busy', 'shopify_order_id' => null, 'taken_over' => false];
            }
            throw $e;
        }

        return ['state' => 'claimed', 'shopify_order_id' => null, 'taken_over' => $takenOver];
    }

    /**
     * Record the Shopify order for every ref of this marketplace order.
     *
     * @param  list<string>  $refs
     */
    public static function complete(string $channel, array $refs, string $shopifyOrderId): void
    {
        $channel = self::channelKey($channel);
        $refs = self::normalizeRefs($refs);
        $shopifyOrderId = trim($shopifyOrderId);
        if ($channel === '' || $refs === [] || $shopifyOrderId === '' || ! self::tableReady()) {
            return;
        }

        $now = now();
        try {
            foreach ($refs as $ref) {
                DB::table(self::TABLE)->updateOrInsert(
                    ['channel' => $channel, 'ref' => $ref],
                    ['shopify_order_id' => $shopifyOrderId, 'claimed_at' => $now, 'updated_at' => $now, 'created_at' => $now]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('ShopifyOrderCreateClaim: could not record created order', [
                'channel' => $channel,
                'refs' => $refs,
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Drop an unfinished claim after Shopify definitely did not create the order (4xx).
     *
     * @param  list<string>  $refs
     */
    public static function release(string $channel, array $refs): void
    {
        $channel = self::channelKey($channel);
        $refs = self::normalizeRefs($refs);
        if ($channel === '' || $refs === []) {
            return;
        }
        if (! self::tableReady()) {
            Cache::forget('mm_shopify_claim:'.$channel.':'.$refs[0]);

            return;
        }
        DB::table(self::TABLE)
            ->where('channel', $channel)
            ->whereIn('ref', $refs)
            ->whereNull('shopify_order_id')
            ->delete();
    }

    /**
     * Point claims at the Shopify order that was kept after a duplicate was cancelled.
     */
    public static function repoint(string $fromShopifyOrderId, string $toShopifyOrderId): void
    {
        if (! self::tableReady() || trim($fromShopifyOrderId) === '' || trim($toShopifyOrderId) === '') {
            return;
        }
        DB::table(self::TABLE)
            ->where('shopify_order_id', trim($fromShopifyOrderId))
            ->update(['shopify_order_id' => trim($toShopifyOrderId), 'updated_at' => now()]);
    }

    /**
     * Shopify definitely did not create the order: a 4xx answer. A 5xx, a timeout or no
     * status may have created it, so the claim is kept until a later search can see it.
     */
    public static function definitelyNotCreated(?int $status): bool
    {
        return $status !== null && $status >= 400 && $status < 500;
    }

    public static function channelKey(string $channel): string
    {
        return mb_substr(strtolower(trim($channel)), 0, 64);
    }

    /**
     * @param  list<string>  $refs
     * @return list<string>
     */
    public static function normalizeRefs(array $refs): array
    {
        $out = [];
        foreach ($refs as $ref) {
            $ref = strtolower(ltrim(trim((string) $ref), '#'));
            if ($ref !== '') {
                $out[mb_substr($ref, 0, 191)] = true;
            }
        }

        return array_keys($out);
    }

    private static function tableReady(): bool
    {
        static $ready = null;
        if ($ready === true) {
            return true;
        }
        try {
            $ready = Schema::hasTable(self::TABLE);
        } catch (\Throwable $e) {
            $ready = false;
        }

        return $ready;
    }

    private static function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[0] ?? $e->getCode());

        return $code === '23000' || $code === '23505' || str_contains(strtolower($e->getMessage()), 'duplicate entry');
    }
}
