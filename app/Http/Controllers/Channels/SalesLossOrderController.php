<?php

namespace App\Http\Controllers\Channels;

use App\Models\ChannelMasterCalculatedData;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\GofoExpressService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\VeeqoApiService;
use App\Support\ProductMasterTemuShip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Sales Loss Order — cancelled / refunded / voided / lost marketplace orders.
 * Same row shape as /sales-order-fulfillment All Order, without the 30-day default.
 */
class SalesLossOrderController extends SalesOrderFulfillmentController
{
    public function index(GofoExpressService $gofo, VeeqoApiService $veeqo): View
    {
        $sloChannels = collect(MarketplaceManagerRegistry::channels())
            ->filter(fn ($c) => ($c['enabled'] ?? false) === true)
            ->map(fn ($c) => [
                'slug' => (string) ($c['slug'] ?? ''),
                'label' => (string) ($c['label'] ?? ($c['slug'] ?? '')),
            ])
            ->filter(fn ($c) => ($c['slug'] ?? '') !== '')
            ->values()
            ->all();

        return view('channels.sales_loss_order', [
            'sloChannels' => $sloChannels,
        ]);
    }

    /**
     * All sales-loss orders across enabled marketplaces.
     * Date range is optional (date_from / date_to). Empty = all history.
     */
    public function data(): JsonResponse
    {
        try {
            @set_time_limit(120);

            $rows = $this->collectOrderRows(
                function (string $slug) {
                    $query = $this->lossOrdersQuery($slug);

                    return $this->maybeScopeByRequestDates($query, $slug);
                },
                false,
                true
            );
            $rows = $this->attachNetProfitPctToRows($rows);
            $rows = $this->attachCostShipDetailsToRows($rows);

            $amountTotal = 0.0;
            $channels = [];
            foreach ($rows as $row) {
                if (is_numeric($row['amount'] ?? null)) {
                    $amountTotal += (float) $row['amount'];
                }
                $slug = (string) ($row['mm_slug'] ?? '');
                if ($slug !== '') {
                    $channels[$slug] = true;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $rows,
                'count' => count($rows),
                'channel_count' => count($channels),
                'amount_total' => round($amountTotal, 2),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load sales loss orders.',
                'data' => [],
                'count' => 0,
                'channel_count' => 0,
                'amount_total' => 0,
            ], 500);
        }
    }

    /**
     * Cancelled / refunded / voided / lost orders for a marketplace.
     */
    protected function lossOrdersQuery(string $slug): ?Builder
    {
        $base = $this->allOrdersQuery($slug);
        if ($base === null) {
            return null;
        }

        return match ($slug) {
            // Newegg: 4 = Voided
            'newegg' => $base->where(function (Builder $q) {
                $q->whereIn('status', ['4', 4])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%CANCEL%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%VOID%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status, ''))) LIKE ?", ['%REFUND%']);
            }),
            'temu', 'temu2' => $base->where(function (Builder $q) {
                foreach (['parent_order_status_text', 'order_status_text'] as $col) {
                    foreach (['%CANCEL%', '%REFUND%', '%VOID%', '%LOST%'] as $needle) {
                        $q->orWhereRaw("UPPER(TRIM(COALESCE({$col}, ''))) LIKE ?", [$needle]);
                    }
                }
            }),
            'doba' => $this->applyLossLikeFilter($base, 'order_status'),
            'aliexpress', 'alibaba' => $this->applyLossLikeFilter($base, 'status', ['%CLOSE%']),
            default => $this->applyLossLikeFilter($base, 'status'),
        };
    }

    /**
     * @param  list<string>  $extraNeedles
     */
    protected function applyLossLikeFilter(Builder $query, string $column, array $extraNeedles = []): Builder
    {
        $needles = array_merge(['%CANCEL%', '%REFUND%', '%VOID%', '%LOST%'], $extraNeedles);

        return $query->where(function (Builder $q) use ($column, $needles) {
            foreach ($needles as $i => $needle) {
                $sql = "UPPER(TRIM(COALESCE({$column}, ''))) LIKE ?";
                if ($i === 0) {
                    $q->whereRaw($sql, [$needle]);
                } else {
                    $q->orWhereRaw($sql, [$needle]);
                }
            }
        });
    }

    /**
     * Apply date_from / date_to only when the request sends them.
     * Unlike SOF, empty dates mean all historical rows.
     */
    protected function maybeScopeByRequestDates(?Builder $query, string $slug): ?Builder
    {
        if ($query === null) {
            return null;
        }

        $fromRaw = trim((string) request()->input('date_from', ''));
        $toRaw = trim((string) request()->input('date_to', ''));
        if ($fromRaw === '' && $toRaw === '') {
            return $query;
        }

        $tz = 'America/Los_Angeles';
        $from = $fromRaw !== ''
            ? $this->parseCaliforniaDateInput($fromRaw, now($tz)->subDays(30)->startOfDay())
            : now($tz)->subYears(20)->startOfDay();
        $to = $toRaw !== ''
            ? $this->parseCaliforniaDateInput($toRaw, now($tz)->endOfDay(), true)
            : now($tz)->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return $this->applyOrderDateRangeFilter($query, $from, $to, $slug);
    }

    /**
     * INV from Shopify plus DIL% = (shopify L30 / INV) × 100.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachInvToOrderRows(array $rows): array
    {
        $rows = parent::attachInvToOrderRows($rows);
        if ($rows === []) {
            return $rows;
        }

        $skus = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $skus[$sku] = true;
            }
        }
        if ($skus === []) {
            return $rows;
        }

        $shopifyBySku = ShopifySku::mapByProductSkus(array_keys($skus));
        foreach ($rows as &$row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $shopify = $sku !== '' ? $shopifyBySku->get($sku) : null;
            $inv = (int) ($row['INV'] ?? 0);
            // Same as /amazon-tabulator-view: OV L30 = shopify_skus.quantity
            $l30 = $shopify ? (int) ($shopify->quantity ?? 0) : 0;
            $row['l30'] = $l30;
            $row['dil'] = $inv > 0 ? round(($l30 / $inv) * 100, 2) : 0.0;
        }
        unset($row);

        return $rows;
    }

    /**
     * Add NROI% / NPFT% from channel_master_calculated_data (same source as GROI/GPFT).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachNetProfitPctToRows(array $rows): array
    {
        $pct = $this->channelProfitPctBySlug();
        foreach ($rows as &$row) {
            $slug = (string) ($row['mm_slug'] ?? '');
            $m = $pct[$slug] ?? [];
            $row['nroi_pct'] = $m['nroi_pct'] ?? null;
            $row['npft_pct'] = $m['npft_pct'] ?? null;
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string, array{groi_pct: ?float, gpft_pct: ?float, nroi_pct: ?float, npft_pct: ?float}>
     */
    protected function channelProfitPctBySlug(): array
    {
        $out = [];
        if (! Schema::hasTable('channel_master_calculated_data')) {
            return $out;
        }

        $byChannel = [];
        try {
            foreach (ChannelMasterCalculatedData::query()->get(['channel', 'g_roi', 'gprofit_pct', 'n_roi', 'n_pft']) as $row) {
                $key = strtolower(trim((string) ($row->channel ?? '')));
                if ($key === '' || array_key_exists($key, $byChannel)) {
                    continue;
                }
                $byChannel[$key] = [
                    'groi_pct' => is_numeric($row->g_roi) ? round((float) $row->g_roi, 2) : null,
                    'gpft_pct' => is_numeric($row->gprofit_pct) ? round((float) $row->gprofit_pct, 2) : null,
                    'nroi_pct' => is_numeric($row->n_roi) ? round((float) $row->n_roi, 2) : null,
                    'npft_pct' => is_numeric($row->n_pft) ? round((float) $row->n_pft, 2) : null,
                ];
            }
        } catch (\Throwable) {
            return $out;
        }

        foreach (MarketplaceManagerRegistry::channels() as $channel) {
            if (! ($channel['enabled'] ?? false)) {
                continue;
            }
            $slug = (string) ($channel['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $metrics = [
                'groi_pct' => null,
                'gpft_pct' => null,
                'nroi_pct' => null,
                'npft_pct' => null,
            ];
            foreach (($channel['mp_channel_keys'] ?? []) as $candidate) {
                $key = strtolower(trim((string) $candidate));
                if ($key !== '' && array_key_exists($key, $byChannel)) {
                    $metrics = $byChannel[$key];
                    break;
                }
            }
            $out[$slug] = $metrics;
        }

        return $out;
    }

    /**
     * CP, freight, LP, ship rates from product_master.Values (same keys as Product Master).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function attachCostShipDetailsToRows(array $rows): array
    {
        if ($rows === [] || ! Schema::hasTable('product_master')) {
            return $rows;
        }

        $skus = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $skus[$sku] = true;
            }
        }
        if ($skus === []) {
            return $rows;
        }

        $scalar = static function (array $values, string ...$keys) {
            $lookup = [];
            foreach ($values as $k => $v) {
                $lookup[strtolower((string) $k)] = $v;
            }
            foreach ($keys as $key) {
                $hit = $lookup[strtolower($key)] ?? null;
                if ($hit === null || $hit === '') {
                    continue;
                }
                if (is_numeric($hit)) {
                    return 0 + $hit;
                }
                $trimmed = trim((string) $hit);
                if ($trimmed === '') {
                    continue;
                }
                $numeric = str_replace(',', '', $trimmed);
                if (is_numeric($numeric)) {
                    return 0 + $numeric;
                }

                return $trimmed;
            }

            return null;
        };

        $byNorm = [];
        try {
            ProductMaster::query()
                ->whereIn('sku', array_keys($skus))
                ->get(['sku', 'Values'])
                ->each(function ($product) use (&$byNorm, $scalar) {
                    $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($product->sku ?? ''));
                    if ($norm === '' || isset($byNorm[$norm])) {
                        return;
                    }
                    $values = is_array($product->Values)
                        ? $product->Values
                        : (is_string($product->Values) ? (json_decode($product->Values, true) ?: []) : []);

                    $l = $scalar($values, 'l');
                    $w = $scalar($values, 'w');
                    $h = $scalar($values, 'h');
                    $frght = $scalar($values, 'frght', 'freight', 'frg');
                    if (($frght === null || $frght === '') && is_numeric($l) && is_numeric($w) && is_numeric($h)) {
                        $cbm = (((float) $l * 2.54) * ((float) $w * 2.54) * ((float) $h * 2.54)) / 1000000;
                        $frght = round($cbm * 200, 2);
                    }

                    $lp = $scalar($values, 'lp');
                    if (($lp === null || $lp === '' || (is_numeric($lp) && (float) $lp <= 0)) && isset($product->lp) && is_numeric($product->lp)) {
                        $lp = (float) $product->lp;
                    }

                    $ship = $scalar($values, 'ship');
                    if (($ship === null || $ship === '') && isset($product->ship) && is_numeric($product->ship)) {
                        $ship = (float) $product->ship;
                    }

                    $shipBb = $scalar($values, 'ship_bb');
                    if (($shipBb === null || $shipBb === '') && isset($product->ship_bb) && is_numeric($product->ship_bb)) {
                        $shipBb = (float) $product->ship_bb;
                    }

                    $storedTemu = ProductMasterTemuShip::stored($values, $product);
                    if ($storedTemu === null) {
                        $storedTemu = $scalar($values, 'ship_temu');
                    }
                    $shipTemu = $storedTemu;
                    if ($shipTemu === null) {
                        $computedTemu = ProductMasterTemuShip::forPricing($values, $product);
                        $shipTemu = $computedTemu > 0 ? $computedTemu : null;
                    } else {
                        $shipTemu = round((float) $shipTemu, 2);
                    }

                    $byNorm[$norm] = [
                        'cp' => $scalar($values, 'cp'),
                        'frght' => is_numeric($frght) ? round((float) $frght, 2) : $frght,
                        'lp' => is_numeric($lp) ? round((float) $lp, 2) : $lp,
                        'ship' => is_numeric($ship) ? round((float) $ship, 2) : $ship,
                        'ship_temu' => $shipTemu,
                        'ship_bb' => is_numeric($shipBb) ? round((float) $shipBb, 2) : $shipBb,
                        'l' => $l,
                        'w' => $w,
                        'h' => $h,
                        'wt_act' => $scalar($values, 'wt_act'),
                        'wt_decl' => $scalar($values, 'wt_decl'),
                    ];
                });
        } catch (\Throwable) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $empty = [
                'cp' => null,
                'frght' => null,
                'lp' => null,
                'ship' => null,
                'ship_temu' => null,
                'ship_bb' => null,
            ];
            $sku = trim((string) ($row['sku'] ?? ''));
            $norm = $sku !== '' ? ShopifySku::normalizeSkuForShopifyLookup($sku) : '';
            $extra = ($norm !== '' && isset($byNorm[$norm])) ? $byNorm[$norm] : $empty;
            foreach ($extra as $k => $v) {
                if (in_array($k, ['l', 'w', 'h', 'wt_act', 'wt_decl'], true)
                    && array_key_exists($k, $row)
                    && $row[$k] !== null
                    && $row[$k] !== '') {
                    continue;
                }
                $row[$k] = $v;
            }
        }
        unset($row);

        return $rows;
    }
}
