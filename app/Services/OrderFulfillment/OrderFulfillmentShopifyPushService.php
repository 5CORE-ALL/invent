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
 * Progress is stored on order_fulfillment_trackings so every row is handled once.
 * A row that has not reached Shopify is never given up on inside MAX_ROW_AGE_DAYS:
 * it waits on shopify_next_try_at, with a longer wait after each failure, so a
 * temporary Shopify error can never leave an order unfulfilled for good.
 */
class OrderFulfillmentShopifyPushService
{
    /** Rows older than this are left alone (the page itself only shows recent orders). */
    public const MAX_ROW_AGE_DAYS = 30;

    /** Doba copies are fulfilled by hand. */
    public const EXCLUDED_SLUGS = ['manual', 'doba'];

    public const MAX_CHANNEL_ATTEMPTS = 6;

    /** Minutes between retries of a row that failed (also the wait for rows without shopify_next_try_at). */
    public const RETRY_COOLDOWN_MINUTES = 45;

    /** Longest wait between retries of a temporary failure. */
    public const MAX_RETRY_MINUTES = 360;

    /** Wait between retries of a mismatch the data must change to fix (re-import, relink). */
    public const MISMATCH_RETRY_MINUTES = 720;

    /** Wait while Shopify has no copy / no open fulfillment order yet (no attempt counted). */
    public const WAITING_RETRY_MINUTES = 30;

    /** Only rows resolved by the page from these sources are pushed. */
    public const PUSHABLE_SOURCES = ['veeqo', 'gofo', '4seller', 'channel', 'shopify', 'manual'];

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
            ->whereNull('shopify_fulfilled_at');
        if ($onlyOrderId === null || trim($onlyOrderId) === '') {
            $this->whereDue($query);
        }
        $this->applyTargetFilters($query, $onlySlug, $onlyOrderId);

        // Never-tried rows first, then whichever has waited longest, so a pile of
        // retries can never starve an order.
        return $query->orderByRaw('shopify_push_checked_at IS NULL DESC')
            ->orderBy('shopify_push_checked_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Rows with tracking inside the push window, per marketplace: fulfilled on
     * Shopify vs still waiting, and the reasons the waiting ones gave.
     *
     * @return array{
     *   by_slug: array<string, array{fulfilled: int, waiting: int, due_now: int}>,
     *   reasons: list<array{slug: string, reason: string, rows: int}>
     * }
     */
    public function statusReport(): array
    {
        $report = ['by_slug' => [], 'reasons' => []];
        if (! Schema::hasTable('order_fulfillment_trackings')) {
            return $report;
        }
        self::ensureColumns();

        $base = fn () => OrderFulfillmentTracking::query()
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->whereNotIn('mm_slug', self::EXCLUDED_SLUGS)
            ->whereIn('source', self::PUSHABLE_SOURCES)
            ->where('created_at', '>=', now()->subDays(self::MAX_ROW_AGE_DAYS));

        foreach ($base()->selectRaw('mm_slug, SUM(shopify_fulfilled_at IS NOT NULL) AS fulfilled, SUM(shopify_fulfilled_at IS NULL) AS waiting')
            ->groupBy('mm_slug')->get() as $r) {
            $report['by_slug'][(string) $r->mm_slug] = [
                'fulfilled' => (int) $r->fulfilled,
                'waiting' => (int) $r->waiting,
                'due_now' => 0,
            ];
        }
        $due = $base()->whereNull('shopify_fulfilled_at');
        $this->whereDue($due);
        foreach ($due->selectRaw('mm_slug, COUNT(*) AS n')->groupBy('mm_slug')->get() as $r) {
            if (isset($report['by_slug'][(string) $r->mm_slug])) {
                $report['by_slug'][(string) $r->mm_slug]['due_now'] = (int) $r->n;
            }
        }
        ksort($report['by_slug']);

        $reasons = [];
        foreach ($base()->whereNull('shopify_fulfilled_at')->get(['mm_slug', 'shopify_push_message']) as $r) {
            $key = (string) $r->mm_slug."\0".self::reasonLabel((string) ($r->shopify_push_message ?? ''));
            $reasons[$key] = ($reasons[$key] ?? 0) + 1;
        }
        arsort($reasons);
        foreach ($reasons as $key => $n) {
            [$slug, $reason] = explode("\0", $key, 2);
            $report['reasons'][] = ['slug' => $slug, 'reason' => $reason, 'rows' => $n];
        }

        return $report;
    }

    /** Push message with order-specific numbers removed, so rows group by cause. */
    public static function reasonLabel(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return 'not tried yet';
        }
        $message = (string) preg_replace('/[A-Z0-9]*\d[A-Z0-9-]{5,}/i', '#', $message);

        return mb_strimwidth($message, 0, 90, '…');
    }

    protected function whereDue($query): void
    {
        $now = now();
        $query->where(function ($q) use ($now) {
            $q->where('shopify_next_try_at', '<=', $now)
                ->orWhere(function ($legacy) use ($now) {
                    $legacy->whereNull('shopify_next_try_at')
                        ->where(function ($c) use ($now) {
                            $c->whereNull('shopify_push_checked_at')
                                ->orWhere('shopify_push_checked_at', '<', $now->copy()->subMinutes(self::RETRY_COOLDOWN_MINUTES));
                        });
                });
        });
    }

    /**
     * Minutes until the next Shopify attempt after the given number of failures.
     */
    public static function retryDelayMinutes(int $attempts, bool $mismatch = false): int
    {
        if ($mismatch) {
            return self::MISMATCH_RETRY_MINUTES;
        }
        $steps = max(0, $attempts - 1);

        return (int) min(self::MAX_RETRY_MINUTES, self::RETRY_COOLDOWN_MINUTES * (2 ** min($steps, 4)));
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
            // No attempt counted: re-enabling the switch lets the row fulfill on the next run.
            $this->markWaiting($row, 'Automatic Shopify fulfillment is turned off for '.$slug.'.', $dryRun);
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
            $this->markShopifyFailure($row, 'Marketplace '.$slug.' order row not found for label → Shopify.', true, $dryRun);
            $out['message'] = 'marketplace order row not found';

            return $out;
        }

        $shopifyOrderId = trim((string) ($ctx['shopify_order_id'] ?? ''));
        if ($shopifyOrderId === '' || str_starts_with($shopifyOrderId, 'manual')) {
            $shopifyOrderId = $this->unlinkedShopifyCopy($row, $slug, $ctx);
        }
        if ($shopifyOrderId === '') {
            // The Shopify copy is often imported hours later (queue backlog): wait without counting an attempt.
            $this->markWaiting($row, 'Not linked to a Shopify order yet.', $dryRun);
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
            if (str_contains(strtolower($message), 'no open fulfillment orders')) {
                // Hold, schedule, or a 3PL request: the fulfillment order often opens later the same day.
                $this->markWaiting($row, $action.': '.$message, $dryRun);
            } else {
                $mismatch = in_array($action, ['sku_mismatch', 'order_id_mismatch', 'order_id_required', 'sku_required', 'not_linked'], true);
                $this->markShopifyFailure($row, $action.': '.$message, $mismatch, $dryRun);
            }
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
        $row->shopify_next_try_at = null;
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

    /**
     * A failure counts an attempt and waits longer each time; it never stops the row.
     */
    protected function markShopifyFailure(OrderFulfillmentTracking $row, string $message, bool $mismatch, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }
        $attempts = min(250, (int) $row->shopify_push_attempts + 1);
        $row->shopify_push_attempts = $attempts;
        $row->shopify_push_checked_at = now();
        $row->shopify_next_try_at = now()->addMinutes(self::retryDelayMinutes($attempts, $mismatch));
        $row->shopify_push_message = mb_substr($message, 0, 255);
        $row->save();
    }

    protected function markWaiting(OrderFulfillmentTracking $row, string $message, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }
        $row->shopify_push_checked_at = now();
        $row->shopify_next_try_at = now()->addMinutes(self::WAITING_RETRY_MINUTES);
        $row->shopify_push_message = mb_substr($message, 0, 255);
        $row->save();
    }

    /**
     * Shopify copy for a marketplace row that never got its Shopify id saved:
     * the id found on an earlier run, else a Shopify search by the marketplace
     * order id (verified against the copy's tags before it is used).
     *
     * @param  array<string, mixed>  $ctx
     */
    protected function unlinkedShopifyCopy(OrderFulfillmentTracking $row, string $slug, array $ctx): string
    {
        $known = trim((string) ($row->shopify_order_id ?? ''));
        if ($known !== '' && ! str_starts_with($known, 'manual')) {
            return $known;
        }

        $ids = is_array($ctx['marketplace_order_ids'] ?? null) ? $ctx['marketplace_order_ids'] : [];
        $orderId = trim((string) $row->order_id);
        if ($orderId !== '') {
            $ids[] = $orderId;
        }
        try {
            return (string) ($this->labels->findShopifyCopyForMarketplaceOrder($slug, $ids) ?? '');
        } catch (\Throwable $e) {
            Log::info('OrderFulfillmentShopifyPush: Shopify copy search failed', ['slug' => $slug, 'order' => $orderId, 'error' => $e->getMessage()]);

            return '';
        }
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
        if (! Schema::hasTable('order_fulfillment_trackings')) {
            return;
        }
        if (! Schema::hasColumn('order_fulfillment_trackings', 'shopify_fulfilled_at')) {
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
        if (! Schema::hasColumn('order_fulfillment_trackings', 'shopify_next_try_at')) {
            Schema::table('order_fulfillment_trackings', function ($table) {
                $table->timestamp('shopify_next_try_at')->nullable()->after('shopify_push_checked_at');
                $table->index('shopify_next_try_at', 'of_tracking_next_try_idx');
            });
        }
    }
}
