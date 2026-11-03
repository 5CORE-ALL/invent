<?php

namespace App\Services\Support;

use App\Models\ChannelTabulatorColumnSetting;
use App\Models\ReverbBumpChangeLog;
use App\Models\ReverbProduct;
use App\Models\ShopifySku;
use App\Services\ChannelPromoPricingService;
use App\Services\ReverbApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Reverb Bump % adjustment checklist, server side:
 *   Sold (RV L30) > 0 → 2%, Sold = 0 → 5% (override — never incremental)
 *   exceptions: low stock, high-volume SKUs, premium items (skipped, S Bump% left unchanged)
 * Every fill / push / skip is written to reverb_bump_change_log (shown on /reverb-bump-log).
 *
 * The Sold→Bump slab rules are the same saved rules as /reverb-pricing (channel_tabulator_column_settings
 * 'reverb_sold_vs_bump'); when nothing is saved yet the checklist 2% / 5% rule is used.
 */
class ReverbBumpRuleService
{
    public const RULES_CHANNEL = 'reverb_sold_vs_bump';

    public const SETTINGS_CHANNEL = 'reverb_bump_rule_settings';

    public const SOLD_BUMP = 2.0;

    public const NO_SALE_BUMP = 5.0;

    private const SLABS = [
        '0' => '0', '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5',
        '6' => '6', '7' => '7', '8-10' => '8–10', 'gt-10' => '> 10',
    ];

    // ------------------------------------------------------------------ rules & settings

    /** @return list<array{key: string, label: string, bump: float}> */
    public static function checklistRules(): array
    {
        $out = [];
        foreach (self::SLABS as $key => $label) {
            $out[] = ['key' => $key, 'label' => $label, 'bump' => $key === '0' ? self::NO_SALE_BUMP : self::SOLD_BUMP];
        }

        return $out;
    }

    /** Saved slab rules (same as /reverb-pricing) or the checklist 2% / 5% rule when nothing is saved. */
    public static function rules(): array
    {
        $saved = self::loadSetting(self::RULES_CHANNEL);
        if (! is_array($saved) || $saved === []) {
            return self::checklistRules();
        }

        return self::normalizeRules($saved);
    }

    public static function saveRules(array $incoming): array
    {
        $rules = self::normalizeRules($incoming);
        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => self::RULES_CHANNEL],
            ['visibility' => $rules, 'column_order' => array_column($rules, 'key')]
        );

        return $rules;
    }

    /** @return array{min_stock: float, high_volume_sold: int, premium_price: float, auto_enabled: bool, review_after_days: int} */
    public static function settings(): array
    {
        $defaults = [
            'min_stock' => 5.0,
            'high_volume_sold' => 10,
            'premium_price' => 0.0,           // 0 = premium check off
            'auto_enabled' => false,          // weekly scheduled apply + push
            'review_after_days' => 14,        // no sales this long after a change → needs price/images/title fix
        ];
        $saved = self::loadSetting(self::SETTINGS_CHANNEL);
        if (! is_array($saved)) {
            return $defaults;
        }

        return self::sanitizeSettings(array_merge($defaults, $saved));
    }

    public static function saveSettings(array $incoming): array
    {
        $merged = self::sanitizeSettings(array_merge(self::settings(), $incoming));
        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => self::SETTINGS_CHANNEL],
            ['visibility' => $merged, 'column_order' => []]
        );

        return $merged;
    }

    private static function sanitizeSettings(array $s): array
    {
        return [
            'min_stock' => max(0.0, (float) ($s['min_stock'] ?? 0)),
            'high_volume_sold' => max(0, (int) ($s['high_volume_sold'] ?? 0)),
            'premium_price' => max(0.0, (float) ($s['premium_price'] ?? 0)),
            'auto_enabled' => filter_var($s['auto_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'review_after_days' => max(1, (int) ($s['review_after_days'] ?? 14)),
        ];
    }

    private static function loadSetting(string $channel): ?array
    {
        try {
            $row = ChannelTabulatorColumnSetting::query()->where('channel_name', $channel)->first();
        } catch (\Throwable) {
            return null;
        }

        return is_array($row?->visibility) ? $row->visibility : null;
    }

    private static function normalizeRules(array $incoming): array
    {
        $byKey = [];
        foreach ($incoming as $item) {
            if (is_array($item) && ($k = (string) ($item['key'] ?? '')) !== '') {
                $byKey[$k] = $item;
            }
        }
        $rules = [];
        foreach (self::checklistRules() as $def) {
            $raw = $byKey[$def['key']]['bump'] ?? null;
            $rules[] = [
                'key' => $def['key'],
                'label' => $def['label'],
                'bump' => is_numeric($raw) ? max(0.0, round((float) $raw, 2)) : $def['bump'],
            ];
        }

        return $rules;
    }

    // ------------------------------------------------------------------ evaluation

    public static function slabKey(int $sold): string
    {
        if ($sold <= 0) {
            return '0';
        }
        if ($sold > 10) {
            return 'gt-10';
        }
        if ($sold >= 8) {
            return '8-10';
        }

        return (string) $sold;
    }

    public static function bumpForSold(int $sold, array $rules): float
    {
        $key = self::slabKey($sold);
        foreach ($rules as $r) {
            if (($r['key'] ?? null) === $key) {
                return max(0.0, (float) $r['bump']);
            }
        }

        return 0.0;
    }

    public static function formatBump(?float $n): ?string
    {
        if ($n === null) {
            return null;
        }

        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.').'%';
    }

    /**
     * @param  array{sold: int, inv: float, price: ?float}  $p
     * @return array{status: string, bump: ?float, reason: ?string}  status: fill | skip
     */
    public static function evaluate(array $p, array $rules, array $settings): array
    {
        $sold = (int) $p['sold'];
        $inv = (float) $p['inv'];
        $price = $p['price'] ?? null;

        if ($settings['min_stock'] > 0 && $inv < $settings['min_stock']) {
            return ['status' => 'skip', 'bump' => null, 'reason' => 'low stock (INV '.rtrim(rtrim(number_format($inv, 2, '.', ''), '0'), '.').' < '.rtrim(rtrim(number_format($settings['min_stock'], 2, '.', ''), '0'), '.').')'];
        }
        if ($settings['high_volume_sold'] > 0 && $sold > $settings['high_volume_sold']) {
            return ['status' => 'skip', 'bump' => null, 'reason' => 'high volume (sold '.$sold.' > '.$settings['high_volume_sold'].')'];
        }
        if ($settings['premium_price'] > 0 && $price !== null && $price > $settings['premium_price']) {
            return ['status' => 'skip', 'bump' => null, 'reason' => 'premium item — review manually (price '.number_format($price, 2, '.', '').')'];
        }

        return [
            'status' => 'fill',
            'bump' => $inv <= 0 ? 0.0 : self::bumpForSold($sold, $rules),
            'reason' => $sold > 0 ? 'performing (sold '.$sold.')' : 'no sales in L30',
        ];
    }

    // ------------------------------------------------------------------ plan / fill / push

    /**
     * Plan for every non-ended Reverb listing (or just $listingIds): current numbers + the rule's verdict.
     *
     * @param  list<string>|null  $listingIds
     * @return list<array<string, mixed>>
     */
    public static function plan(?array $listingIds = null, ?array $rules = null, ?array $settings = null): array
    {
        $rules ??= self::rules();
        $settings ??= self::settings();

        $query = ReverbBumpRawPuller::pullableProductsQuery()->whereNotNull('sku')->where('sku', '!=', '');
        if ($listingIds !== null) {
            $query->whereIn('reverb_listing_id', array_map('strval', $listingIds));
        }
        $products = $query->orderBy('sku')->get(['sku', 'reverb_listing_id', 'r_l30', 'price', 'views', 'bump_bid', 'recommended_bid']);

        $shopify = ShopifySku::mapByProductSkus($products->pluck('sku')->unique()->values()->all());

        $plan = [];
        foreach ($products as $p) {
            $row = [
                'sku' => (string) $p->sku,
                'listing_id' => trim((string) $p->reverb_listing_id),
                'sold' => (int) ($p->r_l30 ?? 0),
                'inv' => (float) ($shopify->get($p->sku)?->inv ?? 0),
                'price' => is_numeric($p->price) ? (float) $p->price : null,
                'views' => $p->views !== null ? (int) $p->views : null,
                'old_s_bump' => $p->recommended_bid,
                'old_live_bump' => $p->bump_bid,
            ];
            $row['eval'] = self::evaluate($row, $rules, $settings);
            $plan[] = $row;
        }

        return $plan;
    }

    public static function newRunId(): string
    {
        return date('YmdHis').'_'.bin2hex(random_bytes(3));
    }

    /**
     * Save S Bump% for every "fill" row, log fills and skips. Skipped rows keep their S Bump%.
     *
     * @param  list<array<string, mixed>>  $plan
     * @return array{filled: int, skipped: int}
     */
    public static function applyFill(array $plan, string $runId, string $source): array
    {
        $filled = 0;
        $skipped = 0;
        $logs = [];
        $now = now();

        foreach ($plan as $row) {
            $ev = $row['eval'];
            if ($ev['status'] === 'fill') {
                $value = self::formatBump($ev['bump']);
                ReverbProduct::query()->where('sku', $row['sku'])->update(['recommended_bid' => $value]);
                $filled++;
                $logs[] = self::logRow($runId, $source, 'fill', 'ok', $row, $value, $ev['reason'], null, $now);
            } else {
                $skipped++;
                $logs[] = self::logRow($runId, $source, 'skip', 'skipped', $row, null, $ev['reason'], null, $now);
            }
        }

        self::insertLogs($logs);

        return ['filled' => $filled, 'skipped' => $skipped];
    }

    /**
     * Synchronous push of every "fill" row to Reverb (scheduled command). Logs each result.
     *
     * @param  list<array<string, mixed>>  $plan
     * @return array{ok: int, failed: int}
     */
    public static function pushPlan(array $plan, string $runId, string $source, ?callable $progress = null): array
    {
        $api = app(ReverbApiService::class);
        $promo = app(ChannelPromoPricingService::class);
        $ok = 0;
        $failed = 0;
        $i = 0;

        foreach ($plan as $row) {
            if (($row['eval']['status'] ?? '') !== 'fill') {
                continue;
            }
            $i++;
            $bump = (float) $row['eval']['bump'];
            $success = false;
            $message = null;
            try {
                $res = $api->applyListingBumpBid($row['sku'], $bump);
                $success = ! empty($res['success']);
                $message = (string) ($res['message'] ?? '');
                if ($success) {
                    self::recordPromoPushed($promo, $row['sku'], (float) ($res['percent'] ?? $bump));
                }
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                Log::warning('Reverb bump rule push exception', ['sku' => $row['sku'], 'error' => $message]);
            }

            $success ? $ok++ : $failed++;
            self::insertLogs([self::logRow(
                $runId, $source, 'push', $success ? 'ok' : 'failed', $row,
                self::formatBump($bump), $row['eval']['reason'], $message, now()
            )]);

            if ($progress) {
                $progress($i, $row['sku'], $success, $message);
            }
            usleep(150000);
        }

        return ['ok' => $ok, 'failed' => $failed];
    }

    /**
     * Log one push done by the /reverb-bump background worker (snapshot taken before the push).
     *
     * @param  array<string, mixed>  $snapshot  from snapshot()
     */
    public static function logPush(string $runId, string $source, string $sku, array $snapshot, float $bump, bool $ok, ?string $message): void
    {
        self::insertLogs([self::logRow(
            $runId, $source, 'push', $ok ? 'ok' : 'failed',
            array_merge(['sku' => $sku], $snapshot),
            self::formatBump($bump), null, $message, now()
        )]);
    }

    /** Cheap per-SKU numbers (taken before a push, so the log has the "before" state). */
    public static function snapshot(string $sku): array
    {
        $p = ReverbProduct::query()->where('sku', $sku)->first(['reverb_listing_id', 'r_l30', 'price', 'views', 'bump_bid', 'recommended_bid']);
        $inv = 0.0;
        try {
            $inv = (float) (ShopifySku::firstForProductSku($sku)?->inv ?? 0);
        } catch (\Throwable) {
            // inventory is only informational in the log
        }

        return [
            'listing_id' => $p ? trim((string) $p->reverb_listing_id) : null,
            'sold' => (int) ($p->r_l30 ?? 0),
            'inv' => $inv,
            'price' => ($p && is_numeric($p->price)) ? (float) $p->price : null,
            'views' => ($p && $p->views !== null) ? (int) $p->views : null,
            'old_s_bump' => $p->recommended_bid ?? null,
            'old_live_bump' => $p->bump_bid ?? null,
        ];
    }

    private static function recordPromoPushed(ChannelPromoPricingService $promo, string $sku, float $bump): void
    {
        try {
            $promo->upsert('reverb', $sku, [
                'push_bump_status' => 'pushed',
                'push_bump_value' => $bump,
                'push_bump_pushed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Reverb bump rule: push ok but promo save failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------ change log

    /** @param  array<string, mixed>  $row */
    private static function logRow(string $runId, string $source, string $action, string $status, array $row, ?string $newBump, ?string $reason, ?string $message, $at): array
    {
        return [
            'run_id' => $runId,
            'source' => $source,
            'action' => $action,
            'status' => $status,
            'sku' => mb_substr((string) $row['sku'], 0, 191),
            'reverb_listing_id' => $row['listing_id'] ?? null,
            'sold' => max(0, (int) ($row['sold'] ?? 0)),
            'inv' => (float) ($row['inv'] ?? 0),
            'price' => $row['price'] ?? null,
            'views' => $row['views'] ?? null,
            'old_s_bump' => isset($row['old_s_bump']) ? mb_substr((string) $row['old_s_bump'], 0, 20) : null,
            'old_live_bump' => isset($row['old_live_bump']) ? mb_substr((string) $row['old_live_bump'], 0, 20) : null,
            'new_bump' => $newBump,
            'reason' => $reason !== null ? mb_substr($reason, 0, 191) : null,
            'message' => $message,
            'created_at' => $at,
        ];
    }

    /** @param  list<array<string, mixed>>  $rows */
    private static function insertLogs(array $rows): void
    {
        if ($rows === [] || ! Schema::hasTable('reverb_bump_change_log')) {
            return;
        }
        try {
            foreach (array_chunk($rows, 500) as $chunk) {
                ReverbBumpChangeLog::query()->insert($chunk);
            }
        } catch (\Throwable $e) {
            Log::warning('Reverb bump change log insert failed', ['error' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------ monitoring

    /**
     * Latest successful "fill" per SKU joined with the current numbers — drives the review lists.
     *
     * @return list<array<string, mixed>>
     */
    public static function monitorRows(): array
    {
        if (! Schema::hasTable('reverb_bump_change_log')) {
            return [];
        }

        $latestIds = DB::table('reverb_bump_change_log')
            ->where('action', 'fill')->where('status', 'ok')
            ->selectRaw('MAX(id) as id')->groupBy('sku');

        $rows = DB::table('reverb_bump_change_log as l')
            ->joinSub($latestIds, 'latest', fn ($j) => $j->on('l.id', '=', 'latest.id'))
            ->leftJoin('reverb_products as p', 'p.sku', '=', 'l.sku')
            ->get([
                'l.sku', 'l.created_at', 'l.new_bump', 'l.old_s_bump', 'l.sold as sold_then', 'l.views as views_then', 'l.price as price_then',
                'p.reverb_listing_id', 'p.product_title', 'p.r_l30 as sold_now', 'p.views as views_now', 'p.price as price_now',
                'p.bump_bid as live_bump', 'p.listing_state',
            ]);

        $settings = self::settings();
        $out = [];
        foreach ($rows as $r) {
            if (strtolower(trim((string) $r->listing_state)) === 'ended') {
                continue;
            }
            $days = $r->created_at ? (int) floor((time() - strtotime((string) $r->created_at)) / 86400) : 0;
            $soldNow = (int) ($r->sold_now ?? 0);
            $newPct = (float) str_replace('%', '', (string) $r->new_bump);

            $review = null;
            if ($soldNow === 0 && $days >= $settings['review_after_days']) {
                $review = 'no_sales';          // still no sales → fix price, images, title
            } elseif ($soldNow > 0 && $newPct > self::SOLD_BUMP) {
                $review = 'move_to_low';       // selling → move 5% → 2% next cycle
            }

            $out[] = [
                'sku' => $r->sku,
                'listing_id' => $r->reverb_listing_id,
                'title' => $r->product_title,
                'changed_at' => $r->created_at,
                'days_since' => $days,
                'new_bump' => $r->new_bump,
                'live_bump' => $r->live_bump,
                'sold_then' => (int) $r->sold_then,
                'sold_now' => $soldNow,
                'views_then' => $r->views_then,
                'views_now' => $r->views_now !== null ? (int) $r->views_now : null,
                'price' => is_numeric($r->price_now) ? (float) $r->price_now : null,
                'review' => $review,
            ];
        }

        return $out;
    }
}
