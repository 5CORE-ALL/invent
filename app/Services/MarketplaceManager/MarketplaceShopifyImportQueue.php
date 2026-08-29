<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use Illuminate\Bus\UniqueLock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * Shared Shopify import dispatch helpers for Marketplace Manager channels.
 */
class MarketplaceShopifyImportQueue
{
    /** Shared floor for auto-import when a channel has no older dedicated cutoff. */
    public const DEFAULT_IMPORT_CUTOFF_DATE = '2026-07-07';

    /**
     * Rows the dispatcher may queue. skipped_closed is included so widening
     * eligibility (e.g. SHIPPED) can pick up rows skipped under the old rules.
     *
     * @var list<string>
     */
    public const DISPATCHABLE_IMPORT_STATUSES = ['ready', 'import_failed', 'failed', 'skipped_closed'];

    public static function defaultImportCutoff(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse(self::DEFAULT_IMPORT_CUTOFF_DATE, 'America/Los_Angeles')->startOfDay();
    }

    public static function shouldDispatchImports(string $slug, bool $requested = false): bool
    {
        return $requested || MarketplaceSyncSettings::canAutoImportToShopify($slug);
    }

    /**
     * Unique locks / crashed workers can leave import_status=queued with no jobs
     * row and no shopify_order_id. Reset those rows when this order's import job
     * is not actually sitting on the dedicated mm-{slug} queue.
     *
     * Do not require the whole queue to be empty — Amazon order-sync / tracking
     * jobs share mm-amazon and would otherwise freeze Shopify imports forever.
     *
     * @param  class-string  $modelClass
     * @param  callable(Builder):void|null  $constrain
     */
    public static function releaseStuckQueued(string $modelClass, string $queue, ?callable $constrain = null): int
    {
        $table = (new $modelClass)->getTable();
        if (! Schema::hasColumn($table, 'import_status')) {
            return 0;
        }

        $query = $modelClass::query()
            ->where('import_status', 'queued')
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            });

        if ($constrain) {
            $constrain($query);
        }

        $ids = $query->limit(4000)->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->values()
            ->all();

        if ($ids === []) {
            return 0;
        }

        $payloads = DB::table('jobs')->where('queue', $queue)->pluck('payload');

        $stuck = [];
        foreach ($ids as $id) {
            $referenced = false;
            foreach ($payloads as $payload) {
                if (self::payloadReferencesId((string) $payload, $id)) {
                    $referenced = true;
                    break;
                }
            }
            if (! $referenced) {
                $stuck[] = $id;
            }
        }

        if ($stuck === []) {
            return 0;
        }

        $updated = 0;
        foreach (array_chunk($stuck, 500) as $chunk) {
            $updated += (int) $modelClass::query()
                ->whereIn('id', $chunk)
                ->where('import_status', 'queued')
                ->where(function ($q) {
                    $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
                })
                ->update(['import_status' => 'ready']);
        }

        return $updated;
    }

    public static function push(object $job, string $queue): void
    {
        try {
            (new UniqueLock(app('cache.store')))->release($job);
        } catch (\Throwable $e) {
            Log::debug('MarketplaceShopifyImportQueue: unique lock release skipped', [
                'error' => $e->getMessage(),
            ]);
        }

        Queue::connection('database')->pushOn($queue, $job);
    }

    /**
     * Network / Shopify 5xx / rate-limit / duplicate-check API failures should
     * retry instead of permanently marking import_failed.
     */
    public static function isRetryableShopifyFailure(?int $status, ?string $reason): bool
    {
        if ($status === 429 || ($status !== null && $status >= 500)) {
            return true;
        }

        $reason = (string) $reason;
        if ($reason === '') {
            return false;
        }

        return stripos($reason, 'cURL error') !== false
            || stripos($reason, 'timed out') !== false
            || stripos($reason, 'Connection refused') !== false
            || stripos($reason, 'SSL certificate') !== false
            || stripos($reason, 'duplicate check') !== false
            || stripos($reason, 'Push blocked to avoid duplicates') !== false;
    }

    /**
     * Rows already linked to Shopify should show imported, not ready/queued/failed.
     *
     * @param  class-string  $modelClass
     * @param  callable(\Illuminate\Database\Eloquent\Builder):void|null  $constrain
     */
    public static function markLinkedAsImported(string $modelClass, ?callable $constrain = null): int
    {
        $table = (new $modelClass)->getTable();
        if (! Schema::hasColumn($table, 'shopify_order_id') || ! Schema::hasColumn($table, 'import_status')) {
            return 0;
        }

        $query = $modelClass::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereNotIn('import_status', ['imported']);
            });

        if ($constrain) {
            $constrain($query);
        }

        return (int) $query->limit(4000)->update(['import_status' => 'imported']);
    }

    /**
     * @param  class-string  $modelClass
     * @param  callable(\Illuminate\Database\Eloquent\Builder):void|null  $constrain
     */
    public static function prepareForDispatch(string $modelClass, string $queue, ?callable $constrain = null): int
    {
        self::markLinkedAsImported($modelClass, $constrain);

        return self::releaseStuckQueued($modelClass, $queue, $constrain);
    }

    /**
     * Queue one Shopify import per marketplace order, newest first.
     * Sibling line rows that already have a Shopify id are linked instead of
     * creating a second order.
     *
     * @param  class-string  $modelClass
     * @param  callable(int):object  $makeJob
     * @param  callable(Builder):void|null  $constrain
     */
    public static function dispatchLatestUnpushed(
        string $slug,
        string $modelClass,
        callable $makeJob,
        string $orderIdColumn = 'order_id',
        ?callable $constrain = null,
        int $scanLimit = 400,
        int $dispatchLimit = 200
    ): int {
        if (! MarketplaceSyncSettings::canAutoImportToShopify($slug)) {
            return 0;
        }

        $queue = MarketplaceManagerRegistry::queueFor($slug);
        self::prepareForDispatch($modelClass, $queue, $constrain);

        $paidOnly = MarketplaceSyncSettings::importPaidOrdersOnly($slug);
        $table = (new $modelClass)->getTable();
        $hasOrderDate = Schema::hasColumn($table, 'order_date');
        $hasImportStatus = Schema::hasColumn($table, 'import_status');

        $query = $modelClass::query()
            ->where(function ($q) {
                $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
            })
            ->where(function ($q) {
                $q->whereNull('import_status')
                    ->orWhereIn('import_status', self::DISPATCHABLE_IMPORT_STATUSES);
            });
        if ($constrain) {
            $constrain($query);
        }
        if ($hasOrderDate) {
            $query->orderByDesc('order_date');
        }
        $query->orderByDesc('id')->limit(max(1, $scanLimit));

        $seen = [];
        $dispatched = 0;
        foreach ($query->get() as $order) {
            $orderId = trim((string) ($order->{$orderIdColumn} ?? ''));
            if ($orderId === '' || isset($seen[$orderId])) {
                continue;
            }
            $seen[$orderId] = true;

            $alreadyImported = $modelClass::query()
                ->where($orderIdColumn, $orderId)
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->value('shopify_order_id');
            if ($alreadyImported) {
                $modelClass::query()
                    ->where($orderIdColumn, $orderId)
                    ->where(function ($q) {
                        $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
                    })
                    ->update([
                        'shopify_order_id' => (string) $alreadyImported,
                        'import_status' => 'imported',
                    ]);
                continue;
            }

            if ($paidOnly && ! MarketplaceOrderPaidFilter::isPaid($slug, $order)) {
                if ($hasImportStatus) {
                    $modelClass::query()
                        ->where($orderIdColumn, $orderId)
                        ->where(function ($q) {
                            $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
                        })
                        ->update(['import_status' => 'skipped_unpaid']);
                }
                continue;
            }

            try {
                self::push($makeJob((int) $order->id), $queue);
                $modelClass::query()
                    ->where($orderIdColumn, $orderId)
                    ->where(function ($q) {
                        $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
                    })
                    ->update(['import_status' => 'queued']);
                $dispatched++;
                if ($dispatched >= $dispatchLimit) {
                    break;
                }
            } catch (\Throwable $e) {
                Log::warning('MarketplaceShopifyImportQueue: failed to queue import', [
                    'slug' => $slug,
                    'id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $dispatched;
    }

    /**
     * True when a database-queue payload still holds this model/job id
     * (PHP-serialized constructor ints, JSON ids, uniqueId suffixes).
     */
    protected static function payloadReferencesId(string $blob, int $id): bool
    {
        if ($blob === '' || $id <= 0) {
            return false;
        }

        $idStr = (string) $id;

        return str_contains($blob, ';i:'.$idStr.';')
            || str_contains($blob, ';s:'.strlen($idStr).':"'.$idStr.'";')
            || (bool) preg_match('/import[-:]'.$idStr.'(?!\d)/', $blob);
    }
}
