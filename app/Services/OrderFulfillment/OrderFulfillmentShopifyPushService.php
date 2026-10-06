<?php

namespace App\Services\OrderFulfillment;

use App\Models\OrderFulfillmentTracking;
use App\Services\MarketplaceManager\MarketplaceChannelFulfillmentHub;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Takes the tracking numbers the Order Fulfillment page has already resolved
 * (Veeqo / GOFO / 4Seller / marketplace / typed by hand) and:
 *
 *  1. fulfils the matching Shopify order with that number (idempotent — Shopify
 *     orders that already carry the number are only marked as done), then
 *  2. pushes the number to the originating marketplace when the marketplace
 *     order has no tracking yet (each channel service checks "already shipped").
 *
 * Progress is stored on order_fulfillment_trackings so every row is handled once
 * and failures retry with a cool-down instead of hammering the APIs.
 */
class OrderFulfillmentShopifyPushService
{
    public const MAX_SHOPIFY_ATTEMPTS = 16;

    /** Rows older than this are left alone (the page itself only shows recent orders). */
    public const MAX_ROW_AGE_DAYS = 30;

    /** Doba copies are fulfilled by hand. */
    public const EXCLUDED_SLUGS = ['manual', 'doba'];

    public const MAX_CHANNEL_ATTEMPTS = 6;

    /** Minutes between retries of a row that failed. */
    public const RETRY_COOLDOWN_MINUTES = 45;

    /** Only rows resolved by the page from these sources are pushed. */
    public const PUSHABLE_SOURCES = ['veeqo', 'gofo', '4seller', 'channel', 'manual'];

    public function __construct(
        protected VeeqoShopifyFulfillmentService $labels,
        protected MarketplaceChannelFulfillmentHub $hub
    ) {
    }

    /**
     * @return array{
     *   checked: int, shopify_fulfilled: int, shopify_already: int, shopify_failed: int,
     *   channel_pushed: int, channel_failed: int, channel_skipped: int, seconds: float,
     *   rows: list<array<string, mixed>>
     * }
     */
    public function run(int $limit = 60, float $budgetSeconds = 540.0, bool $dryRun = false, ?string $onlySlug = null, ?string $onlyOrderId = null): array
    {
        $started = microtime(true);
        $deadline = $started + max(20.0, $budgetSeconds);
        $stats = [
            'checked' => 0,
            'shopify_fulfilled' => 0,
            'shopify_already' => 0,
            'shopify_failed' => 0,
            'channel_pushed' => 0,
            'channel_failed' => 0,
            'channel_skipped' => 0,
            'seconds' => 0.0,
            'rows' => [],
        ];

        if (! Schema::hasTable('order_fulfillment_trackings')) {
            $stats['seconds'] = round(microtime(true) - $started, 1);

            return $stats;
        }
        self::ensureColumns();

        foreach ($this->pendingShopifyRows($limit, $onlySlug, $onlyOrderId) as $row) {
            if (microtime(true) >= $deadline) {
                break;
            }
            $stats['checked']++;
            $outcome = $this->handleRow($row, $dryRun);
            $stats['rows'][] = $outcome;

            match ($outcome['shopify']) {
                'fulfilled' => $stats['shopify_fulfilled']++,
                'already' => $stats['shopify_already']++,
                default => $stats['shopify_failed']++,
            };
            match ($outcome['channel']) {
                'pushed' => $stats['channel_pushed']++,
                'failed' => $stats['channel_failed']++,
                default => $stats['channel_skipped']++,
            };
            // The Shopify REST bucket is shared with every other sync job; pace to avoid 429 retry loops.
            usleep(700000);
        }

        // Rows already on Shopify whose marketplace push did not succeed yet.
        foreach ($this->pendingChannelRows($limit, $onlySlug, $onlyOrderId) as $row) {
            if (microtime(true) >= $deadline) {
                break;
            }
            $stats['checked']++;
            $outcome = $this->retryChannelPush($row, $dryRun);
            $stats['rows'][] = $outcome;
            match ($outcome['channel']) {
                'pushed' => $stats['channel_pushed']++,
                'failed' => $stats['channel_failed']++,
                default => $stats['channel_skipped']++,
            };
            usleep(120000);
        }

        $stats['seconds'] = round(microtime(true) - $started, 1);

        return $stats;
    }

    /**
     * @return \Illuminate\Support\Collection<int, OrderFulfillmentTracking>
     */
    protected function pendingShopifyRows(int $limit, ?string $onlySlug, ?string $onlyOrderId)
    {
        $query = OrderFulfillmentTracking::query()
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->whereNotIn('mm_slug', self::EXCLUDED_SLUGS)
            ->whereIn('source', self::PUSHABLE_SOURCES)
            ->where('created_at', '>=', now()->subDays(self::MAX_ROW_AGE_DAYS))
            ->whereNull('shopify_fulfilled_at')
            ->where('shopify_push_attempts', '<', self::MAX_SHOPIFY_ATTEMPTS)
            ->where(function ($q) {
                $q->whereNull('shopify_push_checked_at')
                    ->orWhere('shopify_push_checked_at', '<', now()->subMinutes(self::RETRY_COOLDOWN_MINUTES));
            });
        $this->applyTargetFilters($query, $onlySlug, $onlyOrderId);

        // Newest orders first so today's / yesterday's orders are not stuck behind old retries.
        return $query->orderByRaw('shopify_push_checked_at IS NULL DESC')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, OrderFulfillmentTracking>
     */
    protected function pendingChannelRows(int $limit, ?string $onlySlug, ?string $onlyOrderId)
    {
        $query = OrderFulfillmentTracking::query()
            ->whereNotNull('shopify_fulfilled_at')
            ->whereNull('channel_pushed_at')
            ->whereNotIn('mm_slug', self::EXCLUDED_SLUGS)
            ->where('created_at', '>=', now()->subDays(self::MAX_ROW_AGE_DAYS))
            ->where('channel_push_attempts', '>', 0)
            ->where('channel_push_attempts', '<', self::MAX_CHANNEL_ATTEMPTS)
            ->where(function ($q) {
                $q->whereNull('shopify_push_checked_at')
                    ->orWhere('shopify_push_checked_at', '<', now()->subMinutes(self::RETRY_COOLDOWN_MINUTES));
            });
        $this->applyTargetFilters($query, $onlySlug, $onlyOrderId);

        return $query->orderBy('shopify_push_checked_at')->orderBy('id')->limit(max(1, $limit))->get();
    }

    protected function applyTargetFilters($query, ?string $onlySlug, ?string $onlyOrderId): void
    {
        if ($onlySlug !== null && trim($onlySlug) !== '') {
            $query->where('mm_slug', strtolower(trim($onlySlug)));
        }
        if ($onlyOrderId !== null && trim($onlyOrderId) !== '') {
            $query->where('order_id', trim($onlyOrderId));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function handleRow(OrderFulfillmentTracking $row, bool $dryRun): array
    {
        $slug = strtolower(trim((string) $row->mm_slug));
        $tracking = strtoupper(trim((string) $row->tracking_number));
        $carrier = trim((string) ($row->carrier ?? ''));
        $out = [
            'row_key' => (string) $row->row_key,
            'marketplace' => $slug,
            'order_id' => (string) $row->order_id,
            'sku' => (string) $row->sku,
            'tracking' => $tracking,
            'shopify' => 'failed',
            'shopify_order_id' => '',
            'channel' => 'skipped',
            'message' => '',
        ];

        $localId = $this->localIdFromRowKey($slug, (string) $row->row_key);
        if ($localId === null) {
            $this->markShopifyFailure($row, 'Could not read the marketplace row id from '.$row->row_key, true, $dryRun);
            $out['message'] = 'row id unreadable';

            return $out;
        }

        if ($this->labels->autoFulfillBlocked($slug)) {
            // Cooldown only (no attempt burned): re-enabling the switch lets the row fulfill later.
            if (! $dryRun) {
                $row->shopify_push_checked_at = now();
                $row->shopify_push_message = mb_substr('Automatic Shopify fulfillment is turned off for '.$slug.'.', 0, 255);
                $row->save();
            }
            $out['shopify'] = 'skipped';
            $out['message'] = 'auto-fulfill off for '.$slug;

            return $out;
        }

        try {
            $ctx = $this->labels->contextForMarketplaceOrder($slug, $localId);
        } catch (\Throwable $e) {
            $ctx = null;
            Log::warning('OrderFulfillmentShopifyPush: context failed', ['slug' => $slug, 'id' => $localId, 'error' => $e->getMessage()]);
        }
        if ($ctx === null) {
            $this->markShopifyFailure($row, 'Marketplace '.$slug.' is not set up for label → Shopify.', true, $dryRun);
            $out['message'] = 'unsupported marketplace';

            return $out;
        }

        $shopifyOrderId = trim((string) ($ctx['shopify_order_id'] ?? ''));
        if ($shopifyOrderId === '' || str_starts_with($shopifyOrderId, 'manual')) {
            // The Shopify copy is often imported hours later (queue backlog): wait without burning attempts.
            if (! $dryRun) {
                $row->shopify_push_checked_at = now();
                $row->shopify_push_message = 'Not linked to a Shopify order yet.';
                $row->save();
            }
            $out['message'] = 'not linked to Shopify yet';

            return $out;
        }
        $out['shopify_order_id'] = $shopifyOrderId;

        if ($dryRun) {
            $out['shopify'] = 'dry-run';
            $out['message'] = 'would fulfil Shopify '.$shopifyOrderId.' with '.$tracking.($carrier !== '' ? ' ('.$carrier.')' : '');

            return $out;
        }

        try {
            $result = $this->labels->fulfillShopifyFromLabels(
                $shopifyOrderId,
                (array) $ctx['shopify_config'],
                (array) ($ctx['refs'] ?? []),
                ['tracking' => $tracking, 'carrier' => $carrier !== '' ? $carrier : 'Other'],
                (string) ($row->sku ?: ($ctx['sku'] ?? '')),
                is_array($ctx['marketplace_order_ids'] ?? null) ? $ctx['marketplace_order_ids'] : [],
                $slug
            );
        } catch (\Throwable $e) {
            $this->markShopifyFailure($row, 'Shopify error: '.$e->getMessage(), false, $dryRun);
            $out['message'] = $e->getMessage();

            return $out;
        }

        $action = (string) ($result['action'] ?? '');
        $message = (string) ($result['message'] ?? '');
        if (! in_array($action, ['shopify_fulfilled', 'already_on_shopify'], true)) {
            // Permanent mismatches (wrong order id / SKU on the Shopify copy) are not retried.
            // "No open fulfillment orders" = already fulfilled / cancelled on Shopify; retrying cannot help.
            $permanent = in_array($action, ['order_id_mismatch', 'sku_mismatch', 'order_id_required', 'sku_required', 'not_linked'], true)
                || str_contains(strtolower($message), 'no open fulfillment orders');
            $this->markShopifyFailure($row, $action.': '.$message, $permanent, $dryRun);
            $out['message'] = $action.': '.$message;

            return $out;
        }

        $out['shopify'] = $action === 'shopify_fulfilled' ? 'fulfilled' : 'already';
        $out['message'] = $message;
        $resultTracking = strtoupper(trim((string) ($result['tracking'] ?? $tracking)));
        $resultCarrier = trim((string) ($result['carrier'] ?? $carrier));

        $row->shopify_order_id = $shopifyOrderId;
        $row->shopify_fulfilled_at = now();
        $row->shopify_push_checked_at = now();
        $row->shopify_push_message = mb_substr($message, 0, 255);
        $row->save();

        try {
            $this->labels->persistTrackingOntoMarketplaceOrder($slug, $localId, $shopifyOrderId, $resultTracking, $resultCarrier);
        } catch (\Throwable $e) {
            Log::debug('OrderFulfillmentShopifyPush: persist onto marketplace row failed', ['slug' => $slug, 'id' => $localId, 'error' => $e->getMessage()]);
        }

        $push = $this->pushToChannel($row, $slug, $localId, [
            'tracking' => $resultTracking,
            'carrier' => $resultCarrier,
            'sku' => (string) $row->sku,
            'action' => $action,
            'success' => true,
        ]);
        $out['channel'] = $push['state'];
        $out['channel_message'] = $push['message'];

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    protected function retryChannelPush(OrderFulfillmentTracking $row, bool $dryRun): array
    {
        $slug = strtolower(trim((string) $row->mm_slug));
        $out = [
            'row_key' => (string) $row->row_key,
            'marketplace' => $slug,
            'order_id' => (string) $row->order_id,
            'sku' => (string) $row->sku,
            'tracking' => (string) $row->tracking_number,
            'shopify' => 'already',
            'shopify_order_id' => (string) $row->shopify_order_id,
            'channel' => 'skipped',
            'message' => 'retry marketplace push',
        ];
        $localId = $this->localIdFromRowKey($slug, (string) $row->row_key);
        if ($localId === null) {
            return $out;
        }
        if ($dryRun) {
            $out['channel'] = 'dry-run';

            return $out;
        }
        $push = $this->pushToChannel($row, $slug, $localId, [
            'tracking' => strtoupper(trim((string) $row->tracking_number)),
            'carrier' => trim((string) ($row->carrier ?? '')),
            'sku' => (string) $row->sku,
            'action' => 'already_on_shopify',
            'success' => true,
        ]);
        $out['channel'] = $push['state'];
        $out['channel_message'] = $push['message'];

        return $out;
    }

    /**
     * @param  array<string, mixed>  $known
     * @return array{state: string, message: string}
     */
    protected function pushToChannel(OrderFulfillmentTracking $row, string $slug, int $localId, array $known): array
    {
        $row->shopify_push_checked_at = now();

        if ($slug === 'pls') {
            // The Shopify order fulfilled above is the PLS storefront order itself.
            $row->channel_pushed_at = now();
            $row->channel_push_message = 'Fulfilled on the PLS Shopify store.';
            $row->save();

            return ['state' => 'pushed', 'message' => $row->channel_push_message];
        }

        if (! $this->hub->supportsChannel($slug)) {
            $row->channel_push_attempts = self::MAX_CHANNEL_ATTEMPTS;
            $row->channel_push_message = 'No marketplace tracking push available for '.$slug.'.';
            $row->save();

            return ['state' => 'skipped', 'message' => $row->channel_push_message];
        }

        if (! $this->hub->channelPushEnabled($slug)) {
            // Cooldown only (no attempt burned) so the row is pushed once the setting is switched back on.
            $row->channel_push_attempts = max(1, (int) $row->channel_push_attempts);
            $row->channel_push_message = 'Tracking push is turned off for '.$slug.' in Marketplace Manager settings.';
            $row->save();

            return ['state' => 'skipped', 'message' => $row->channel_push_message];
        }

        try {
            $result = $this->hub->pushAfterShopifyTracking($slug, $localId, $known);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        if ($result === null) {
            $row->channel_push_attempts = min(self::MAX_CHANNEL_ATTEMPTS, (int) $row->channel_push_attempts + 1);
            $row->channel_push_message = 'Marketplace order row not found for '.$slug.'.';
            $row->save();

            return ['state' => 'failed', 'message' => $row->channel_push_message];
        }

        $message = trim((string) ($result['message'] ?? ''));
        $notImplemented = str_contains(strtolower($message), 'not implemented')
            || (string) ($result['action'] ?? '') === 'not_implemented';

        if (! empty($result['success'])) {
            $row->channel_pushed_at = now();
            $row->channel_push_message = mb_substr($message !== '' ? $message : 'Pushed.', 0, 255);
            $row->save();

            return ['state' => 'pushed', 'message' => $row->channel_push_message];
        }

        $row->channel_push_attempts = $notImplemented
            ? self::MAX_CHANNEL_ATTEMPTS
            : min(self::MAX_CHANNEL_ATTEMPTS, (int) $row->channel_push_attempts + 1);
        $row->channel_push_message = mb_substr($message !== '' ? $message : 'Marketplace push failed.', 0, 255);
        $row->save();

        return ['state' => $notImplemented ? 'skipped' : 'failed', 'message' => $row->channel_push_message];
    }

    protected function markShopifyFailure(OrderFulfillmentTracking $row, string $message, bool $permanent, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }
        $row->shopify_push_attempts = $permanent
            ? self::MAX_SHOPIFY_ATTEMPTS
            : min(self::MAX_SHOPIFY_ATTEMPTS, (int) $row->shopify_push_attempts + 1);
        $row->shopify_push_checked_at = now();
        $row->shopify_push_message = mb_substr($message, 0, 255);
        $row->save();
    }

    /**
     * Row keys are "{slug}-{marketplace row id}" or "{slug}-{row id}-{item id}" (Amazon).
     */
    protected function localIdFromRowKey(string $slug, string $rowKey): ?int
    {
        if ($slug === '' || ! preg_match('/^'.preg_quote($slug, '/').'-(\d+)(?:-|$)/', $rowKey, $m)) {
            return null;
        }
        $id = (int) $m[1];

        return $id > 0 ? $id : null;
    }

    public static function ensureColumns(): void
    {
        if (! Schema::hasTable('order_fulfillment_trackings')
            || Schema::hasColumn('order_fulfillment_trackings', 'shopify_fulfilled_at')) {
            return;
        }
        Schema::table('order_fulfillment_trackings', function ($table) {
            $table->string('shopify_order_id', 64)->nullable()->after('checked_at');
            $table->timestamp('shopify_fulfilled_at')->nullable()->after('shopify_order_id');
            $table->unsignedTinyInteger('shopify_push_attempts')->default(0)->after('shopify_fulfilled_at');
            $table->timestamp('shopify_push_checked_at')->nullable()->after('shopify_push_attempts');
            $table->string('shopify_push_message', 255)->nullable()->after('shopify_push_checked_at');
            $table->timestamp('channel_pushed_at')->nullable()->after('shopify_push_message');
            $table->unsignedTinyInteger('channel_push_attempts')->default(0)->after('channel_pushed_at');
            $table->string('channel_push_message', 255)->nullable()->after('channel_push_attempts');
        });
    }
}
