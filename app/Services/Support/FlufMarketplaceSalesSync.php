<?php

namespace App\Services\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Vinted and Depop are connected to Shopify through the Fluf app, so every sale there
 * shows up as a Shopify order. This copies those orders from shopify_raw_orders into the
 * /vinted/sheet and /depop/sheet tables, one sheet row per order line, so the sheets
 * (and everything reading them: L30, Y sales, analytics) stay current without an upload.
 *
 * A Fluf order is told apart by the channel name Fluf stamps on it: the order's
 * source_name or one of its tags contains "vinted" or "depop".
 */
class FlufMarketplaceSalesSync
{
    /** @var array<string, array{table: string, label: string, fee: string}> */
    public const CHANNELS = [
        'vinted' => ['table' => 'vinted_sales_data', 'label' => 'Vinted', 'fee' => 'vinted_fee'],
        'depop' => ['table' => 'depop_sales_data', 'label' => 'Depop', 'fee' => 'depop_fee'],
    ];

    /** Order states that are not a sale. */
    private const NOT_A_SALE_STATUS = ['voided', 'refunded'];

    /**
     * Which channel a Shopify order belongs to, from its source_name and tags. Null = not Vinted/Depop.
     */
    public static function channelFor(?string $sourceName, ?string $tags): ?string
    {
        // Only the tag (or source) naming the channel matters: Fluf orders and the manual draft orders
        // created before Fluf was connected are both counted.
        $text = strtolower(trim((string) $sourceName.' '.(string) $tags));
        if ($text === '') {
            return null;
        }
        $hasVinted = str_contains($text, 'vinted');
        $hasDepop = str_contains($text, 'depop');
        if ($hasVinted && ! $hasDepop) {
            return 'vinted';
        }
        if ($hasDepop && ! $hasVinted) {
            return 'depop';
        }

        return null;
    }

    /**
     * @return array{channel: string, label: string, found: int, added: int, updated: int, removed: int, skipped: int, manual_overlap: int, from: string, to: string, message: string}
     */
    public function sync(string $channel, int $days = 60, bool $replaceUploads = false): array
    {
        $channel = strtolower(trim($channel));
        $cfg = self::CHANNELS[$channel] ?? null;
        if ($cfg === null) {
            throw new \InvalidArgumentException('Unknown channel "'.$channel.'". Use vinted or depop.');
        }
        $table = $cfg['table'];
        $from = Carbon::now()->subDays(max(1, $days))->startOfDay();
        $to = Carbon::now()->endOfDay();

        $result = [
            'channel' => $channel, 'label' => $cfg['label'], 'found' => 0, 'added' => 0, 'updated' => 0,
            'removed' => 0, 'skipped' => 0, 'manual_overlap' => 0,
            'from' => $from->toDateString(), 'to' => $to->toDateString(), 'message' => '',
        ];

        if (! Schema::hasTable($table) || ! Schema::hasTable('shopify_raw_orders')) {
            $result['message'] = $cfg['label'].': sheet table or shopify_raw_orders is missing.';

            return $result;
        }
        if (! Schema::hasColumn($table, 'shopify_order_id')) {
            $result['message'] = $cfg['label'].': run php artisan migrate first (Shopify columns are missing).';

            return $result;
        }

        $term = $channel;
        $lines = DB::table('shopify_raw_orders')
            ->where('order_date', '>=', $from->toDateString())
            ->where('order_date', '<=', $to->toDateString())
            ->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(COALESCE(source_name,"")) LIKE ?', ['%'.$term.'%'])
                    ->orWhereRaw('LOWER(COALESCE(tags,"")) LIKE ?', ['%'.$term.'%']);
            })
            ->get();

        $resolveSku = $this->skuResolver($lines);

        $keep = [];
        foreach ($lines as $line) {
            if (self::channelFor($line->source_name ?? null, $line->tags ?? null) !== $channel) {
                $result['skipped']++;

                continue;
            }
            $status = strtolower(trim((string) ($line->financial_status ?? '')));
            $tags = strtolower((string) ($line->tags ?? ''));
            if (in_array($status, self::NOT_A_SALE_STATUS, true) || str_contains($tags, 'cancel')) {
                $result['skipped']++;

                continue;
            }
            $qty = max(1, (int) $line->quantity);
            $net = round((float) ($line->net_sales ?? 0), 2);
            if ($net <= 0) {
                $net = round((float) ($line->total_amount ?? 0), 2);
            }
            $row = [
                'sale_date' => $line->order_date,
                'buyer' => $line->customer_name ?: null,
                'description' => $line->product_title ?: null,
                'quantity' => $qty,
                'item_price' => round($net / $qty, 2),
                'total' => $net,
                'sku_code' => $resolveSku($line)['sku'] ?: null,
                'source' => 'shopify',
                'shopify_order_number' => $line->order_number ?: null,
            ];
            $key = ['shopify_order_id' => (int) $line->order_id, 'shopify_line_item_id' => (int) $line->line_item_id];
            $keep[$key['shopify_order_id'].':'.$key['shopify_line_item_id']] = true;
            $result['found']++;

            $existing = DB::table($table)->where($key)->first(['id']);
            if ($existing) {
                DB::table($table)->where('id', $existing->id)->update($row + ['updated_at' => now()]);
                $result['updated']++;
            } else {
                DB::table($table)->insert($row + $key + ['created_at' => now(), 'updated_at' => now()]);
                $result['added']++;
            }
        }

        // Orders that were cancelled / refunded since the last run no longer count.
        $stale = DB::table($table)
            ->whereNotNull('shopify_order_id')
            ->where('sale_date', '>=', $from->toDateString())
            ->get(['id', 'shopify_order_id', 'shopify_line_item_id']);
        $staleIds = [];
        foreach ($stale as $row) {
            if (! isset($keep[$row->shopify_order_id.':'.$row->shopify_line_item_id])) {
                $staleIds[] = $row->id;
            }
        }
        if ($staleIds !== []) {
            DB::table($table)->whereIn('id', $staleIds)->delete();
            $result['removed'] = count($staleIds);
        }

        // Rows from an earlier CSV upload that cover the same dates: they are NOT touched, but they would
        // count the same sale twice, so say so.
        $first = DB::table($table)->whereNotNull('shopify_order_id')->min('sale_date');
        if ($first !== null) {
            $overlap = DB::table($table)->whereNull('shopify_order_id')->where('sale_date', '>=', $first);
            if ($replaceUploads) {
                $result['replaced_uploads'] = (int) $overlap->delete();
            } else {
                $result['manual_overlap'] = (int) $overlap->count();
            }
        }

        $this->refreshDependents($channel);

        $result['message'] = $cfg['label'].': '.$result['found'].' Shopify order line(s) in '.$from->toDateString().' → '.$to->toDateString()
            .' (added '.$result['added'].', updated '.$result['updated'].', removed '.$result['removed'].')'
            .(($result['replaced_uploads'] ?? 0) > 0
                ? '. Removed '.$result['replaced_uploads'].' uploaded row(s) dated on or after the first Shopify sale (Shopify now covers them).'
                : ($result['manual_overlap'] > 0
                    ? '. '.$result['manual_overlap'].' uploaded row(s) fall on or after the first Shopify sale and may double count (run fluf:sync-sales '.$channel.' --replace-uploads to drop them).'
                    : '.'));

        return $result;
    }

    /**
     * Builds a function that returns the exact SKU for an order line.
     *
     * The SKU printed on an order line is a snapshot taken when the order was created, and Fluf creates its
     * own line items, so it can differ from the SKU the Shopify variant carries today (or be blank). The
     * Shopify variant is the source of truth, so the candidates are tried in this order and the first one
     * that is a real Product Master SKU wins (returned in Product Master spelling, so NBSP / hyphen / case
     * differences no longer break the join):
     *   1. the SKU on the order line
     *   2. the variant's current SKU in shopify_skus (by variant_id)
     *   3. the variant's current SKU in shopify_catalog_variants (by variant_id)
     * If none is a Product Master SKU, the variant's current SKU is used when the line has none or it is
     * unknown, otherwise the line SKU is kept.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $lines
     * @return \Closure(object): array{sku: string, source: string, line_sku: string, shopify_sku: string, catalog_sku: string, in_pm: bool}
     */
    public function skuResolver($lines): \Closure
    {
        $clean = static fn ($v) => trim(str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xE2\x80\x87", "\xE2\x80\x8B"], ' ', (string) $v));

        $pm = [];
        $compact = [];
        if (Schema::hasTable('product_master')) {
            foreach (DB::table('product_master')->whereNull('deleted_at')->whereNotNull('sku')->pluck('sku') as $s) {
                $s = trim((string) $s);
                if ($s === '' || stripos($s, 'PARENT') === 0) {
                    continue;
                }
                $pm[\App\Models\ShopifySku::normalizeSkuForShopifyLookup($s)] ??= $s;
                $compact[\App\Models\ShopifySku::compactSkuForLookup($s)] ??= $s;
            }
        }

        $variantIds = collect($lines)->pluck('variant_id')->filter()->map(fn ($v) => (string) $v)->unique()->values()->all();
        $shopifySku = [];
        $catalogSku = [];
        foreach (array_chunk($variantIds, 1000) as $chunk) {
            if (Schema::hasTable('shopify_skus')) {
                foreach (DB::table('shopify_skus')->whereIn('variant_id', $chunk)->get(['variant_id', 'sku']) as $r) {
                    if (trim((string) $r->sku) !== '') {
                        $shopifySku[(string) $r->variant_id] = $clean($r->sku);
                    }
                }
            }
            if (Schema::hasTable('shopify_catalog_variants')) {
                foreach (DB::table('shopify_catalog_variants')->whereIn('shopify_variant_id', $chunk)->get(['shopify_variant_id', 'sku']) as $r) {
                    if (trim((string) $r->sku) !== '') {
                        $catalogSku[(string) $r->shopify_variant_id] = $clean($r->sku);
                    }
                }
            }
        }

        $toPm = static function (string $sku) use ($pm, $compact): ?string {
            if ($sku === '') {
                return null;
            }

            return $pm[\App\Models\ShopifySku::normalizeSkuForShopifyLookup($sku)]
                ?? $compact[\App\Models\ShopifySku::compactSkuForLookup($sku)]
                ?? null;
        };

        // Product title -> Product Master SKUs, for lines Fluf created without a variant (custom items whose
        // "SKU" is just the Vinted listing code, e.g. "500W - 5 Core 6" 2 Way Coaxial Car Door Speakers ...").
        $titleKey = static fn ($t) => preg_replace('/[^a-z0-9]+/', '', strtolower((string) $t));
        $titleSkus = [];
        if (Schema::hasTable('shopify_skus')) {
            foreach (DB::table('shopify_skus')->whereNotNull('sku')->where('sku', '!=', '')->get(['sku', 'product_title']) as $r) {
                $k = $titleKey($r->product_title);
                $pmSku = $toPm($clean($r->sku));
                if ($k !== '' && $pmSku !== null) {
                    $titleSkus[$k][$pmSku] = true;
                }
            }
        }
        $byTitle = static function (string $title, string $lineSku) use ($titleKey, $titleSkus): array {
            $t = trim($title);
            if ($lineSku !== '' && stripos($t, $lineSku) === 0) {
                $t = ltrim(substr($t, strlen($lineSku)), " \t-–—:");
            }
            $k = $titleKey($t);
            if ($k === '') {
                return [];
            }
            if (isset($titleSkus[$k])) {
                return array_keys($titleSkus[$k]);
            }
            // Titles are often shortened / edited on the listing: accept a long common start.
            $probe = substr($k, 0, 45);
            $found = [];
            if (strlen($probe) >= 30) {
                foreach ($titleSkus as $tk => $skus) {
                    if (str_starts_with($tk, $probe)) {
                        $found += $skus;
                    }
                }
            }

            return array_keys($found);
        };

        return function ($line) use ($clean, $shopifySku, $catalogSku, $toPm, $byTitle): array {
            $vid = (string) ($line->variant_id ?? '');
            $lineSku = $clean($line->sku ?? '');
            $sSku = $shopifySku[$vid] ?? '';
            $cSku = $catalogSku[$vid] ?? '';
            $base = ['line_sku' => $lineSku, 'shopify_sku' => $sSku, 'catalog_sku' => $cSku, 'candidates' => []];

            foreach (['order line' => $lineSku, 'shopify_skus' => $sSku, 'catalog variant' => $cSku] as $source => $cand) {
                $hit = $toPm($cand);
                if ($hit !== null) {
                    return ['sku' => $hit, 'source' => $source, 'in_pm' => true] + $base;
                }
            }

            $fallback = $lineSku !== '' ? $lineSku : ($sSku !== '' ? $sSku : $cSku);

            // A variant-backed SKU that is simply not in Product Master (e.g. an open-box listing) is a real
            // Shopify SKU: keep it. Only variant-less lines are guessed from the product title.
            if ($vid === '' || $fallback === $lineSku && $sSku === '' && $cSku === '') {
                $cands = $byTitle((string) ($line->product_title ?? ''), $lineSku);
                if (count($cands) === 1) {
                    return ['sku' => $cands[0], 'source' => 'matched by product title', 'in_pm' => true] + array_merge($base, ['candidates' => $cands]);
                }
                if (count($cands) > 1) {
                    return ['sku' => $fallback, 'source' => 'ambiguous title ('.count($cands).' SKUs)', 'in_pm' => false] + array_merge($base, ['candidates' => $cands]);
                }
            }

            return ['sku' => $fallback, 'source' => 'no Product Master match', 'in_pm' => false] + $base;
        };
    }

    /**
     * Per Shopify order line of a channel: the SKU on the line, the variant's current Shopify SKU and what
     * the sheet will use. Changes nothing.
     *
     * @return list<array<string, mixed>>
     */
    public function skuAudit(string $channel, int $days = 365): array
    {
        $channel = strtolower($channel);
        if (! isset(self::CHANNELS[$channel]) || ! Schema::hasTable('shopify_raw_orders')) {
            return [];
        }
        $from = Carbon::now()->subDays(max(1, $days))->toDateString();
        $lines = DB::table('shopify_raw_orders')
            ->where('order_date', '>=', $from)
            ->where(function ($q) use ($channel) {
                $q->whereRaw('LOWER(COALESCE(source_name,"")) LIKE ?', ['%'.$channel.'%'])
                    ->orWhereRaw('LOWER(COALESCE(tags,"")) LIKE ?', ['%'.$channel.'%']);
            })
            ->orderByDesc('order_date')
            ->get()
            ->filter(fn ($l) => self::channelFor($l->source_name ?? null, $l->tags ?? null) === $channel)
            ->values();

        $resolve = $this->skuResolver($lines);
        $out = [];
        foreach ($lines as $l) {
            $r = $resolve($l);
            $out[] = $r + [
                'order' => (string) $l->order_number,
                'date' => (string) $l->order_date,
                'title' => (string) $l->product_title,
                'variant_id' => (string) $l->variant_id,
                'raw_line_sku' => (string) $l->sku,
            ];
        }

        return $out;
    }

    /**
     * What Fluf-like orders look like in shopify_raw_orders, so the matching can be checked.
     *
     * @return list<array{source_name: string, tags: string, channel: string, orders: int, lines: int, first: string, last: string}>
     */
    public function inspect(int $days = 60): array
    {
        if (! Schema::hasTable('shopify_raw_orders')) {
            return [];
        }
        $from = Carbon::now()->subDays(max(1, $days))->toDateString();
        $rows = DB::table('shopify_raw_orders')
            ->where('order_date', '>=', $from)
            ->where(function ($q) {
                foreach (['fluf', 'vinted', 'depop'] as $term) {
                    $q->orWhereRaw('LOWER(COALESCE(source_name,"")) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(COALESCE(tags,"")) LIKE ?', ['%'.$term.'%']);
                }
            })
            ->selectRaw('COALESCE(source_name,"") as source_name, COALESCE(tags,"") as tags, COUNT(DISTINCT order_id) as orders, COUNT(*) as line_count, MIN(order_date) as first_date, MAX(order_date) as last_date')
            ->groupBy('source_name', 'tags')
            ->orderByDesc('orders')
            ->limit(60)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'source_name' => (string) $row->source_name,
                'tags' => (string) $row->tags,
                'channel' => self::channelFor($row->source_name, $row->tags) ?? '— not matched —',
                'orders' => (int) $row->orders,
                'lines' => (int) $row->line_count,
                'first' => (string) $row->first_date,
                'last' => (string) $row->last_date,
            ];
        }

        return $out;
    }

    /**
     * Totals per channel and month straight from shopify_raw_orders (no 60-group cap, unlike inspect()),
     * plus what the sheet table holds for the same month. Shows where sales are lost.
     *
     * @return list<array{channel: string, month: string, orders: int, lines: int, qty: int, not_a_sale: int, sheet_rows: int}>
     */
    public function summary(string $channel, int $days = 365): array
    {
        $cfg = self::CHANNELS[strtolower($channel)] ?? null;
        if ($cfg === null || ! Schema::hasTable('shopify_raw_orders')) {
            return [];
        }
        $channel = strtolower($channel);
        $from = Carbon::now()->subDays(max(1, $days))->toDateString();

        $lines = DB::table('shopify_raw_orders')
            ->where('order_date', '>=', $from)
            ->where(function ($q) use ($channel) {
                $q->whereRaw('LOWER(COALESCE(source_name,"")) LIKE ?', ['%'.$channel.'%'])
                    ->orWhereRaw('LOWER(COALESCE(tags,"")) LIKE ?', ['%'.$channel.'%']);
            })
            ->get(['order_id', 'order_date', 'quantity', 'source_name', 'tags', 'financial_status']);

        $byMonth = [];
        foreach ($lines as $line) {
            if (self::channelFor($line->source_name ?? null, $line->tags ?? null) !== $channel) {
                continue;
            }
            $month = substr((string) $line->order_date, 0, 7);
            $byMonth[$month] ??= ['orders' => [], 'lines' => 0, 'qty' => 0, 'not_a_sale' => 0];
            $byMonth[$month]['orders'][$line->order_id] = true;
            $byMonth[$month]['lines']++;
            $status = strtolower(trim((string) ($line->financial_status ?? '')));
            if (in_array($status, self::NOT_A_SALE_STATUS, true) || str_contains(strtolower((string) $line->tags), 'cancel')) {
                $byMonth[$month]['not_a_sale']++;
            } else {
                $byMonth[$month]['qty'] += max(1, (int) $line->quantity);
            }
        }

        $sheetByMonth = [];
        if (Schema::hasTable($cfg['table'])) {
            foreach (DB::table($cfg['table'])->where('sale_date', '>=', $from)->get(['sale_date']) as $r) {
                $m = substr((string) $r->sale_date, 0, 7);
                $sheetByMonth[$m] = ($sheetByMonth[$m] ?? 0) + 1;
            }
        }

        ksort($byMonth);
        $out = [];
        foreach ($byMonth as $month => $v) {
            $out[] = [
                'channel' => $channel, 'month' => $month, 'orders' => count($v['orders']), 'lines' => $v['lines'],
                'qty' => $v['qty'], 'not_a_sale' => $v['not_a_sale'], 'sheet_rows' => $sheetByMonth[$month] ?? 0,
            ];
        }

        return $out;
    }

    /**
     * Look for the same sale entered twice on a sheet, without changing anything.
     *
     * - upload_vs_shopify: a CSV-uploaded row and a Shopify row with the same date + SKU + quantity.
     * - shopify_vs_shopify: two Shopify rows (different orders) with the same date + SKU + quantity + price,
     *   e.g. a manual draft order and a Fluf order for the same sale.
     * - upload_vs_upload: the same date + buyer + SKU + price twice inside the uploaded rows.
     *
     * @return array<string, mixed>
     */
    public function duplicateReport(string $channel): array
    {
        $cfg = self::CHANNELS[strtolower($channel)] ?? null;
        if ($cfg === null || ! Schema::hasTable($cfg['table']) || ! Schema::hasColumn($cfg['table'], 'shopify_order_id')) {
            return ['label' => $cfg['label'] ?? $channel, 'error' => 'sheet table or Shopify columns missing (run migrate)'];
        }
        $rows = DB::table($cfg['table'])->get(['id', 'sale_date', 'buyer', 'sku_code', 'quantity', 'item_price', 'shopify_order_id', 'shopify_order_number']);

        $sku = static fn ($r) => strtoupper(trim((string) $r->sku_code));
        $shopify = $rows->filter(fn ($r) => $r->shopify_order_id !== null);
        $uploads = $rows->filter(fn ($r) => $r->shopify_order_id === null);

        $shopifyByKey = [];
        foreach ($shopify as $r) {
            $shopifyByKey[$r->sale_date.'|'.$sku($r).'|'.(int) $r->quantity][] = $r;
        }

        $uploadVsShopify = [];
        foreach ($uploads as $r) {
            $k = $r->sale_date.'|'.$sku($r).'|'.(int) $r->quantity;
            if ($sku($r) !== '' && isset($shopifyByKey[$k])) {
                $uploadVsShopify[] = 'upload #'.$r->id.' '.$r->sale_date.' '.$r->sku_code.' x'.$r->quantity.' ↔ Shopify '.($shopifyByKey[$k][0]->shopify_order_number ?? '?');
            }
        }

        $shopifyVsShopify = [];
        $seen = [];
        foreach ($shopify as $r) {
            $k = $r->sale_date.'|'.$sku($r).'|'.(int) $r->quantity.'|'.number_format((float) $r->item_price, 2, '.', '');
            if ($sku($r) === '') {
                continue;
            }
            if (isset($seen[$k]) && (string) $seen[$k]->shopify_order_id !== (string) $r->shopify_order_id) {
                $shopifyVsShopify[] = $r->sale_date.' '.$r->sku_code.' x'.$r->quantity.' $'.number_format((float) $r->item_price, 2).' in '.($seen[$k]->shopify_order_number ?? '?').' and '.($r->shopify_order_number ?? '?');
            }
            $seen[$k] ??= $r;
        }

        $uploadVsUpload = [];
        $seenUp = [];
        foreach ($uploads as $r) {
            $k = $r->sale_date.'|'.strtolower(trim((string) $r->buyer)).'|'.$sku($r).'|'.number_format((float) $r->item_price, 2, '.', '');
            if (isset($seenUp[$k])) {
                $uploadVsUpload[] = 'uploads #'.$seenUp[$k].' and #'.$r->id.': '.$r->sale_date.' '.$r->sku_code.' '.$r->buyer;
            }
            $seenUp[$k] ??= $r->id;
        }

        return [
            'label' => $cfg['label'],
            'shopify_rows' => $shopify->count(),
            'upload_rows' => $uploads->count(),
            'upload_vs_shopify' => $uploadVsShopify,
            'shopify_vs_shopify' => $shopifyVsShopify,
            'upload_vs_upload' => $uploadVsUpload,
        ];
    }

    private function refreshDependents(string $channel): void
    {
        try {
            if ($channel === 'vinted') {
                \App\Http\Controllers\MarketPlace\VintedController::syncL30FromSales();
            } else {
                app(\App\Http\Controllers\Channels\ChannelMasterController::class)->refreshDepopYSalesAfterSheetUpload();
            }
        } catch (\Throwable $e) {
            Log::warning('Fluf '.$channel.' sheet follow-up refresh failed: '.$e->getMessage());
        }
    }
}
