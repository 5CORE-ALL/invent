<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Sales\AmazonSalesController;
use App\Models\AmazonDatasheet;
use App\Models\AmazonOrder;
use App\Models\ChannelMaster;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Support\AmazonDilPriceCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Promotions — Tabulator of active channels from channel_master
 * (same source as /all-marketplace-master Active Channels Master).
 *
 * Column order:
 *   Img | Channels | Status | <dynamic columns…> | Notes and Remarks | Edit (pen)
 *
 * Dynamic columns live in promotion_columns; their per-channel values and the
 * Notes and Remarks text live in promotion_channel_data.
 */
class PromotionsController extends Controller
{
    public function index()
    {
        return view('market-places.promotions');
    }

    /**
     * Whether a seller can issue a coupon on a marketplace. Matched on the
     * normalized channel name by prefix (so "Temu 2", "eBay 3" etc. follow their
     * parent). Channels not listed return null (shown as "—", not guessed).
     *
     * Auto-fills any dynamic column whose header contains "coupon"; a manual
     * Yes/No saved from the pen button overrides it.
     */
    private const COUPON_SUPPORT = [
        // Sellers can create coupons / promo codes.
        // Checked against seller docs (Oct 2026): newegg (Seller Portal > Marketing > Promotion Code),
        // macys (Mirakl seller promotions with promo_code), walmart (Promo Code / Extra Savings
        // discounts, eligibility applies), temu (Marketing Center > Coupons), shein (store coupons;
        // may need account permission).
        'amazon' => true,
        'ebay' => true,
        'shopify' => true,
        'tiktok' => true,
        'aliexpress' => true,
        'faire' => true,
        'etsy' => true,
        'reverb' => true,
        'google' => true,
        'newegg' => true,
        'macy' => true,       // matches "Macy", "Macy's", "Macys"
        'walmart' => true,
        'temu' => true,
        'shein' => true,
        // Confirmed no seller-created coupons
        'wayfair' => false,   // suppliers only join Wayfair-run promotions
        'mercari' => false,   // platform-issued coupons only
        'poshmark' => false,  // no seller coupon codes
        // Not confirmed either way -> left out so they show "—" (unknown) instead of a guessed ✖:
        // bestbuy (sellers set a Discount Price, no promo code found), doba, purchasingpower,
        // pls, depop, vinted, instagram.
    ];

    private function couponSupport(string $channel): ?bool
    {
        $key = preg_replace('/[^a-z0-9]/', '', strtolower($channel));
        foreach (self::COUPON_SUPPORT as $prefix => $allowed) {
            if (str_starts_with($key, $prefix)) {
                return $allowed;
            }
        }

        return null;
    }

    /**
     * Channels that have the "EL30 Dil % Coupons" rule modal
     * (partials/ebay-zero-sold-coupon). Value = the partial's channel key.
     * Exact match on the normalized channel name.
     */
    private const COUPON_RULE_MODAL = [
        'ebay' => 'ebay1',
        'ebay1' => 'ebay1',
        'ebay2' => 'ebay2',
        'ebaytwo' => 'ebay2',
        'ebay3' => 'ebay3',
        'ebaythree' => 'ebay3',
        // Amazon: Dil % × Price coupon rule (partials/amazon-dil-price-coupon)
        'amazon' => 'amazon',
    ];

    private function couponRuleModal(string $channel): ?string
    {
        $key = preg_replace('/[^a-z0-9]/', '', strtolower($channel));

        return self::COUPON_RULE_MODAL[$key] ?? null;
    }

    private function storageReady(): bool
    {
        return Schema::hasTable('promotion_columns') && Schema::hasTable('promotion_channel_data');
    }

    public function data(Request $request)
    {
        try {
            if (! Schema::hasTable('channel_master')) {
                return response()->json(['success' => true, 'data' => [], 'columns' => [], 'count' => 0]);
            }

            $hasLogo = Schema::hasColumn('channel_master', 'logo');
            $hasSellerLink = Schema::hasColumn('channel_master', 'seller_link');
            $hasAlias = Schema::hasColumn('channel_master', 'alias');

            $columns = ['id', 'channel', 'status'];
            if ($hasLogo) {
                $columns[] = 'logo';
            }
            if ($hasSellerLink) {
                $columns[] = 'seller_link';
            }
            if ($hasAlias) {
                $columns[] = 'alias';
            }

            $rows = ChannelMaster::query()
                ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                ->whereNotNull('channel')
                ->where('channel', '!=', '')
                ->orderBy('channel')
                ->get($columns);

            $dynamicColumns = [];
            $extra = collect();
            if ($this->storageReady()) {
                $dynamicColumns = DB::table('promotion_columns')
                    ->orderBy('position')
                    ->orderBy('id')
                    ->get(['id', 'name'])
                    ->map(fn ($c) => ['id' => (int) $c->id, 'name' => $c->name])
                    ->values()
                    ->all();

                $extra = DB::table('promotion_channel_data')
                    ->whereIn('channel_master_id', $rows->pluck('id'))
                    ->get()
                    ->keyBy('channel_master_id');
            }

            $data = $rows->map(function ($row) use ($hasLogo, $hasSellerLink, $hasAlias, $extra) {
                $stored = $extra->get($row->id);
                $values = $stored && $stored->values ? (json_decode($stored->values, true) ?: []) : [];

                return [
                    'id' => $row->id,
                    'image' => $hasLogo ? ($row->logo ?? null) : null,
                    'channel' => trim((string) $row->channel),
                    'alias' => $hasAlias ? ($row->alias ?? null) : null,
                    'seller_link' => $hasSellerLink ? ($row->seller_link ?? null) : null,
                    'status' => 'Active',
                    'coupon_allowed' => $this->couponSupport((string) $row->channel),
                    'coupon_rule' => $this->couponRuleModal((string) $row->channel),
                    'notes_remarks' => $stored->notes_remarks ?? '',
                    'values' => (object) $values,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'columns' => $dynamicColumns,
                'count' => $data->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Promotions data failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a dynamic column with a user-chosen header name.
     */
    public function storeColumn(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
        ]);

        if (! $this->storageReady()) {
            return response()->json([
                'success' => false,
                'message' => 'Promotions tables are missing. Run php artisan migrate.',
            ], 500);
        }

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Column name is required.'], 422);
        }

        $reserved = ['img', 'channels', 'channel', 'status', 'notes and remarks', 'edit'];
        $lower = mb_strtolower($name);
        $exists = DB::table('promotion_columns')->whereRaw('LOWER(name) = ?', [$lower])->exists();
        if ($exists || in_array($lower, $reserved, true)) {
            return response()->json(['success' => false, 'message' => 'A column with this name already exists.'], 422);
        }

        $position = (int) DB::table('promotion_columns')->max('position') + 1;
        $id = DB::table('promotion_columns')->insertGetId([
            'name' => $name,
            'position' => $position,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'column' => ['id' => $id, 'name' => $name],
        ]);
    }

    /**
     * Save Notes and Remarks + every dynamic column value for one channel (pen button).
     */
    public function saveRow(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:channel_master,id',
            'notes_remarks' => 'nullable|string|max:5000',
            'values' => 'nullable|array',
            'values.*' => 'nullable|string|max:2000',
        ]);

        if (! $this->storageReady()) {
            return response()->json([
                'success' => false,
                'message' => 'Promotions tables are missing. Run php artisan migrate.',
            ], 500);
        }

        $validIds = DB::table('promotion_columns')->pluck('id')->map(fn ($v) => (string) $v)->all();
        $values = [];
        foreach ((array) $request->input('values', []) as $columnId => $value) {
            if (in_array((string) $columnId, $validIds, true)) {
                $values[(string) $columnId] = $value === null ? '' : (string) $value;
            }
        }

        $channelId = $request->integer('id');
        $notes = (string) $request->input('notes_remarks', '');

        DB::table('promotion_channel_data')->updateOrInsert(
            ['channel_master_id' => $channelId],
            [
                'notes_remarks' => $notes,
                'values' => json_encode((object) $values),
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $channelId,
                'notes_remarks' => $notes,
                'values' => (object) $values,
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Amazon: Dil % × Price coupon rule (SKU based)
    // ------------------------------------------------------------------

    private const AMAZON_COUPON_SETTING = 'amazon_dil_price_coupon';

    private const AMAZON_COUPON_HISTORY = 'amazon_dil_price_coupon_history';

    private const AMAZON_COUPON_HISTORY_DAYS = 400;

    /**
     * Saved rule rows (or the defaults, all off) + SKU rows + daily count history.
     * SKU row = [A L30, INV, Amazon price]; the page derives Dil % = A L30 ÷ INV × 100.
     */
    public function amazonCouponShow()
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        try {
            $rules = $this->amazonCouponRules();
            $skus = $this->amazonCouponSkus();

            // Record today's per-rule SKU counts (last write of the day wins).
            if ($skus !== []) {
                $this->recordAmazonCouponCounts($this->amazonCouponServerCounts($rules, $skus));
            }

            return response()->json([
                'success' => true,
                'slabs' => $rules,
                'skus' => $skus,
                'today' => now()->toDateString(),
                'days' => $this->amazonCouponHistoryDays(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Promotions Amazon coupon load failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Save the rule rows. Optional counts = [{dmin,dmax,pmin,pmax,count}] (the page's live
     * per-rule SKU counts) are stored as today's history point.
     * Stores the rule only: nothing is pushed to Amazon.
     */
    public function amazonCouponSave(Request $request)
    {
        $request->validate([
            'slabs' => 'required|array|min:1|max:'.AmazonDilPriceCoupon::MAX_RULES,
            'counts' => 'nullable|array|max:'.AmazonDilPriceCoupon::MAX_RULES,
        ]);

        $rules = AmazonDilPriceCoupon::normalize($request->input('slabs'));
        if ($rules === []) {
            return response()->json(['success' => false, 'message' => 'No valid rules.'], 422);
        }

        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => self::AMAZON_COUPON_SETTING],
            ['visibility' => ['slabs' => $rules], 'column_order' => []]
        );

        $counts = [];
        foreach ((array) $request->input('counts', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (['dmin', 'dmax', 'pmin', 'pmax', 'count'] as $field) {
                if (! is_numeric($row[$field] ?? null)) {
                    continue 2;
                }
            }
            $dmin = (float) $row['dmin'];
            $dmax = (float) $row['dmax'];
            $pmin = (float) $row['pmin'];
            $pmax = (float) $row['pmax'];
            if ($dmax < $dmin) {
                [$dmin, $dmax] = [$dmax, $dmin];
            }
            if ($pmax < $pmin) {
                [$pmin, $pmax] = [$pmax, $pmin];
            }
            $counts[AmazonDilPriceCoupon::ruleKey($dmin, $dmax, $pmin, $pmax)] = max(0, (int) $row['count']);
        }
        if ($counts !== []) {
            $this->recordAmazonCouponCounts($counts);
        }

        return response()->json([
            'success' => true,
            'slabs' => $rules,
            'today' => now()->toDateString(),
            'days' => $this->amazonCouponHistoryDays(),
        ]);
    }

    /**
     * @return list<array{dmin:float,dmax:float,pmin:float,pmax:float,pct:int}>
     */
    private function amazonCouponRules(): array
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', self::AMAZON_COUPON_SETTING)
            ->first();
        $vis = is_array($row?->visibility) ? $row->visibility : [];
        $rules = AmazonDilPriceCoupon::normalize($vis['slabs'] ?? null);

        return $rules !== [] ? $rules : AmazonDilPriceCoupon::defaults();
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function amazonCouponHistoryDays(): array
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', self::AMAZON_COUPON_HISTORY)
            ->first();
        $vis = is_array($row?->visibility) ? $row->visibility : [];
        $days = is_array($vis['days'] ?? null) ? $vis['days'] : [];
        ksort($days);

        return $days;
    }

    /**
     * @param  array<string, int>  $counts  rule key => SKU count
     */
    private function recordAmazonCouponCounts(array $counts): void
    {
        $today = now()->toDateString();

        DB::transaction(function () use ($counts, $today) {
            $row = ChannelTabulatorColumnSetting::query()
                ->where('channel_name', self::AMAZON_COUPON_HISTORY)
                ->lockForUpdate()
                ->first();
            $vis = $row && is_array($row->visibility) ? $row->visibility : [];
            $days = is_array($vis['days'] ?? null) ? $vis['days'] : [];
            $days[$today] = array_merge(is_array($days[$today] ?? null) ? $days[$today] : [], $counts);
            ksort($days);
            if (count($days) > self::AMAZON_COUPON_HISTORY_DAYS) {
                $days = array_slice($days, -self::AMAZON_COUPON_HISTORY_DAYS, null, true);
            }
            ChannelTabulatorColumnSetting::query()->updateOrCreate(
                ['channel_name' => self::AMAZON_COUPON_HISTORY],
                ['visibility' => ['days' => $days], 'column_order' => []]
            );
        });
    }

    /**
     * @param  list<array{dmin:float,dmax:float,pmin:float,pmax:float,pct:int}>  $rules
     * @param  list<array{0:int,1:float,2:float}>  $skus
     * @return array<string, int>
     */
    private function amazonCouponServerCounts(array $rules, array $skus): array
    {
        $totals = array_fill(0, count($rules), 0);
        foreach ($skus as [$l30, $inv, $price]) {
            $i = AmazonDilPriceCoupon::matchIndex((float) $l30, (float) $inv, (float) $price, $rules);
            if ($i >= 0) {
                $totals[$i]++;
            }
        }
        $out = [];
        foreach ($rules as $i => $rule) {
            $out[AmazonDilPriceCoupon::ruleKey($rule['dmin'], $rule['dmax'], $rule['pmin'], $rule['pmax'])] = $totals[$i];
        }

        return $out;
    }

    /**
     * Amazon.com listing price (our offer) by SKU; same source the Amazon grid prefers.
     *
     * @return array<string, float>
     */
    private function amazonSitePrices(): array
    {
        if (! Schema::hasTable('amazon_buybox_data')) {
            return [];
        }
        $map = [];
        foreach (DB::table('amazon_buybox_data')->where('our_listing_price', '>', 0)->get(['sku', 'our_listing_price']) as $row) {
            $sku = strtoupper(trim(str_replace("\xC2\xA0", ' ', (string) $row->sku)));
            if ($sku === '') {
                continue;
            }
            $price = round((float) $row->our_listing_price, 2);
            $map[$sku] = $price;
            $compact = AmazonDatasheet::normalizeSkuForLookup($sku);
            if ($compact !== '') {
                $map[$compact] = $price;
            }
        }

        return $map;
    }

    /**
     * One row per SKU that can take part: [A L30, INV, price]. Parents, INV = 0 and SKUs
     * without an Amazon price are left out.
     *
     * @return list<array{0:int,1:float,2:float}>
     */
    private function amazonCouponSkus(): array
    {
        $productSkus = ProductMaster::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->pluck('sku')
            ->filter(fn ($s) => ! str_contains(strtoupper((string) $s), 'PARENT'))
            ->unique()
            ->values()
            ->all();
        if ($productSkus === []) {
            return [];
        }

        $shopify = ShopifySku::mapByProductSkus($productSkus);
        $sheetsBySku = AmazonDatasheet::groupedByNormalizedSku();
        $sitePrices = $this->amazonSitePrices();

        $l30BySku = [];
        try {
            [$start, $end] = AmazonOrder::dailySalesL30Window(AmazonSalesController::DAILY_SALES_WINDOW_DAYS);
            $l30BySku = AmazonOrder::unitsSoldBySkuForWindow($start, $end);
        } catch (\Throwable $e) {
            Log::warning('Promotions Amazon coupon: A L30 units failed: '.$e->getMessage());
        }

        $out = [];
        foreach ($productSkus as $sku) {
            $inv = (float) ($shopify[$sku]->inv ?? 0);
            if (! ($inv > 0)) {
                continue;
            }

            $clean = strtoupper(str_replace("\xC2\xA0", ' ', trim((string) $sku)));
            $lookupKey = str_replace(' ', '', $clean);
            $sheetKey = AmazonDatasheet::normalizeSkuForLookup((string) $sku);
            $sheet = AmazonDatasheet::pickBestForProductSku(
                (string) $sku,
                $sheetsBySku->get($sheetKey)
                    ?? $sheetsBySku->get($lookupKey)
                    ?? $sheetsBySku->get($clean)
            );

            $price = $sheet ? (float) ($sheet->price ?? 0) : 0.0;
            $site = $sitePrices[strtoupper(trim((string) $sku))] ?? $sitePrices[$clean] ?? $sitePrices[$sheetKey] ?? null;
            if ($site !== null && $site > 0) {
                $price = $site;
            }
            if (! ($price > 0)) {
                continue;
            }

            $l30 = AmazonOrder::unitsSoldForProductSku((string) $sku, $l30BySku, $sheet->sku ?? null);
            $out[] = [(int) $l30, round($inv, 2), round($price, 2)];
        }

        return $out;
    }
}
