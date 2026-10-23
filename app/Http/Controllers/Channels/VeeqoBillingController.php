<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\VeeqoBillingShipment;
use App\Services\Veeqo\OrderSkuLookup;
use App\Services\Veeqo\VeeqoBillingSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VeeqoBillingController extends Controller
{
    /** Sortable / filterable table columns (field => column). */
    private const COLUMNS = [
        'shipment_id', 'allocation_id', 'order_id', 'order_number', 'order_status', 'channel_name', 'channel_type',
        'warehouse_name', 'order_created_at', 'order_shipped_at', 'shipment_created_at', 'outbound_shipment_date',
        'carrier_id', 'carrier_name', 'carrier_slug', 'provider_type', 'sub_carrier_id', 'service_type', 'service_name',
        'short_service_name', 'service_carrier_name', 'tracking_number', 'tracking_status', 'tracking_billed',
        'tracking_cancelled', 'tracking_used', 'in_transit_at', 'delivered_at', 'cancelled_at', 'returned_to_sender_at',
        'label_cost', 'label_currency', 'surcharge_total', 'carrier_fee_total', 'total_cost', 'insured_value',
        'shipment_weight', 'allocation_weight', 'weight_unit', 'has_return_label', 'shipped_by', 'order_total_price',
        'order_subtotal_price', 'order_delivery_cost', 'order_currency', 'delivery_method', 'line_item_count', 'skus',
        'veeqo_updated_at',
    ];

    private const SEARCHABLE = ['order_number', 'tracking_number', 'skus', 'channel_name', 'service_name', 'sub_carrier_id', 'shipped_by'];

    public function index()
    {
        return view('channels.veeqo-billing');
    }

    /**
     * Tabulator remote pagination: ?page=&size=&sort[0][field]=&sort[0][dir]=&filter[0][field]=&filter[0][value]=
     */
    public function data(Request $request)
    {
        if (! Schema::hasTable('veeqo_billing_shipments')) {
            return response()->json(['last_page' => 1, 'data' => [], 'total' => 0]);
        }

        $size = (int) $request->input('size', 100);
        $size = $size > 0 ? min($size, 5000) : 100;
        $query = $this->filtered($request);

        foreach ((array) $request->input('sort', []) as $sort) {
            $field = (string) ($sort['field'] ?? '');
            if (in_array($field, self::COLUMNS, true)) {
                $query->orderBy($field, strtolower((string) ($sort['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc');
            }
        }
        $query->orderByDesc('shipment_created_at')->orderByDesc('shipment_id');

        $page = $query->paginate($size, ['*'], 'page', max(1, (int) $request->input('page', 1)));
        $rows = collect($page->items())->map(function (VeeqoBillingShipment $row) {
            $out = $row->only(self::COLUMNS);
            foreach ((array) ($row->charges ?? []) as $charge) {
                $id = strtoupper(trim((string) ($charge['chargeId'] ?? '')));
                if ($id !== '') {
                    $out['charge_'.$id] = round((float) ($out['charge_'.$id] ?? 0) + (float) ($charge['value'] ?? 0), 2);
                }
            }
            foreach (['order_created_at', 'order_shipped_at', 'shipment_created_at', 'outbound_shipment_date', 'in_transit_at', 'delivered_at', 'cancelled_at', 'returned_to_sender_at', 'veeqo_updated_at'] as $date) {
                $out[$date] = $row->{$date}?->format('Y-m-d H:i');
            }

            return $out;
        });

        $noSku = $rows->filter(fn ($r) => trim((string) ($r['skus'] ?? '')) === '' && ! empty($r['order_number']))->pluck('order_number')->unique()->values()->all();
        if ($noSku !== []) {
            $skus = app(OrderSkuLookup::class)->forOrders($noSku);
            $rows = $rows->map(function ($r) use ($skus) {
                if (trim((string) ($r['skus'] ?? '')) === '' && isset($skus[$r['order_number'] ?? ''])) {
                    $r['skus'] = $skus[$r['order_number']];
                }

                return $r;
            });
        }

        return response()->json([
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'data' => $rows,
        ]);
    }

    public function summary(Request $request)
    {
        if (! Schema::hasTable('veeqo_billing_shipments')) {
            return response()->json(['totals' => [], 'charge_ids' => [], 'sync' => []]);
        }

        $totals = $this->filtered($request)
            ->selectRaw('COUNT(*) as shipments')
            ->selectRaw('COUNT(DISTINCT order_id) as orders')
            ->selectRaw('SUM(CASE WHEN label_cost IS NOT NULL THEN 1 ELSE 0 END) as labelled')
            ->selectRaw('COALESCE(SUM(label_cost), 0) as label_cost')
            ->selectRaw('COALESCE(SUM(surcharge_total), 0) as surcharge_total')
            ->selectRaw('COALESCE(SUM(carrier_fee_total), 0) as carrier_fee_total')
            ->selectRaw('COALESCE(SUM(total_cost), 0) as total_cost')
            ->selectRaw('MIN(shipment_created_at) as first_at')
            ->selectRaw('MAX(shipment_created_at) as last_at')
            ->toBase()
            ->first();

        return response()->json([
            'totals' => $totals,
            'charge_ids' => $this->chargeIds(),
            'filters' => [
                'carriers' => $this->distinct('sub_carrier_id'),
                'channels' => $this->distinct('channel_name'),
                'statuses' => $this->distinct('tracking_status'),
            ],
            'sync' => app(VeeqoBillingSync::class)->status() + [
                'rows' => VeeqoBillingShipment::query()->count(),
                'last_row_at' => VeeqoBillingShipment::query()->max('updated_at'),
            ],
        ]);
    }

    public function show(int $shipmentId)
    {
        $row = VeeqoBillingShipment::query()->where('shipment_id', $shipmentId)->firstOrFail();

        return response()->json(['shipment' => $row->raw_shipment, 'row' => $row->only(self::COLUMNS)]);
    }

    /**
     * Pull orders updated since the last sync. The 1-year backfill runs from the command line.
     */
    public function syncNow()
    {
        $lock = Cache::lock('veeqo_billing_sync_now', 900);
        if (! $lock->get()) {
            return response()->json(['success' => false, 'message' => 'A Veeqo billing sync is already running.'], 409);
        }
        try {
            @set_time_limit(900);
            Artisan::call('veeqo:sync-billing');
            $output = trim(Artisan::output());
            $lines = array_values(array_filter(explode("\n", $output)));

            return response()->json(['success' => true, 'message' => end($lines) ?: 'Sync finished.']);
        } finally {
            $lock->release();
        }
    }

    private function filtered(Request $request): Builder
    {
        $query = VeeqoBillingShipment::query();

        $from = trim((string) $request->input('from', ''));
        $to = trim((string) $request->input('to', ''));
        if ($from !== '') {
            $query->where('shipment_created_at', '>=', $from.' 00:00:00');
        }
        if ($to !== '') {
            $query->where('shipment_created_at', '<=', $to.' 23:59:59');
        }
        foreach (['sub_carrier_id' => 'carrier', 'channel_name' => 'channel', 'tracking_status' => 'status'] as $column => $param) {
            $value = trim((string) $request->input($param, ''));
            if ($value !== '') {
                $query->where($column, $value);
            }
        }
        $label = (string) $request->input('label', '');
        if ($label === 'with') {
            $query->whereNotNull('label_cost');
        } elseif ($label === 'without') {
            $query->whereNull('label_cost');
        }
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                foreach (self::SEARCHABLE as $column) {
                    $q->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        foreach ((array) $request->input('filter', []) as $filter) {
            $field = (string) ($filter['field'] ?? '');
            $value = $filter['value'] ?? null;
            if (! in_array($field, self::COLUMNS, true) || $value === null || $value === '') {
                continue;
            }
            $query->where($field, 'like', '%'.$value.'%');
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private function chargeIds(): array
    {
        return Cache::remember('veeqo_billing_charge_ids', 600, function () {
            $ids = [];
            VeeqoBillingShipment::query()
                ->whereNotNull('charges')
                ->where('charges', '!=', '[]')
                ->select(['id', 'charges'])
                ->orderByDesc('id')
                ->limit(20000)
                ->get()
                ->each(function ($row) use (&$ids) {
                    foreach ((array) $row->charges as $charge) {
                        $id = strtoupper(trim((string) ($charge['chargeId'] ?? '')));
                        if ($id !== '') {
                            $ids[$id] = true;
                        }
                    }
                });
            $ids = array_keys($ids);
            usort($ids, fn ($a, $b) => $a === 'BASE_RATE' ? -1 : ($b === 'BASE_RATE' ? 1 : strcmp($a, $b)));

            return $ids;
        });
    }

    /**
     * @return list<string>
     */
    private function distinct(string $column): array
    {
        return DB::table('veeqo_billing_shipments')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->limit(200)
            ->pluck($column)
            ->map(fn ($v) => (string) $v)
            ->all();
    }
}
