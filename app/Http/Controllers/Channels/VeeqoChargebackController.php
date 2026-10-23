<?php

namespace App\Http\Controllers\Channels;

use App\Http\Controllers\Controller;
use App\Models\VeeqoChargeback;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VeeqoChargebackController extends Controller
{
    private const COLUMNS = [
        'transaction_id', 'transaction_type', 'description', 'transaction_status', 'posted_at', 'amazon_order_id',
        'settlement_id', 'financial_event_group_id', 'marketplace_name', 'total_amount', 'currency', 'direction',
        'item_types', 'customer_charge', 'carrier_charge',
    ];

    private const SEARCHABLE = ['amazon_order_id', 'settlement_id', 'transaction_id', 'item_types'];

    public function index()
    {
        return view('channels.veeqo-chargebacks');
    }

    public function data(Request $request)
    {
        if (! Schema::hasTable('veeqo_chargebacks')) {
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
        $query->orderByDesc('posted_at')->orderByDesc('id');

        $page = $query->paginate($size, ['*'], 'page', max(1, (int) $request->input('page', 1)));
        $shipments = $this->shipmentsFor(collect($page->items())->pluck('amazon_order_id')->filter()->unique()->values()->all());

        $rows = collect($page->items())->map(function (VeeqoChargeback $row) use ($shipments) {
            $out = ['id' => $row->id] + $row->only(self::COLUMNS);
            $out['posted_at'] = $row->posted_at?->format('Y-m-d H:i');
            foreach ((array) ($row->components ?? []) as $type => $amount) {
                $out['c_'.$type] = $amount;
            }

            return $out + ($shipments[$row->amazon_order_id] ?? []);
        });

        return response()->json([
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'data' => $rows,
        ]);
    }

    public function summary(Request $request)
    {
        if (! Schema::hasTable('veeqo_chargebacks')) {
            return response()->json(['totals' => [], 'components' => [], 'filters' => [], 'sync' => []]);
        }

        $totals = $this->filtered($request)
            ->selectRaw('COUNT(*) as rows_count')
            ->selectRaw('COUNT(DISTINCT amazon_order_id) as orders')
            ->selectRaw("SUM(CASE WHEN direction = 'debit' THEN 1 ELSE 0 END) as debits")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'debit' THEN total_amount END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN total_amount END), 0) as credit_total")
            ->selectRaw('COALESCE(SUM(total_amount), 0) as net_total')
            ->selectRaw('COALESCE(SUM(customer_charge), 0) as customer_total')
            ->selectRaw('COALESCE(SUM(carrier_charge), 0) as carrier_total')
            ->toBase()
            ->first();

        return response()->json([
            'totals' => $totals,
            'components' => $this->componentTypes(),
            'filters' => [
                'types' => $this->distinct('description'),
                'statuses' => $this->distinct('transaction_status'),
            ],
            'sync' => [
                'rows' => VeeqoChargeback::query()->count(),
                'first_posted_at' => VeeqoChargeback::query()->min('posted_at'),
                'last_posted_at' => VeeqoChargeback::query()->max('posted_at'),
                'last_sync_at' => VeeqoChargeback::query()->max('updated_at'),
            ],
        ]);
    }

    public function show(int $id)
    {
        $row = VeeqoChargeback::query()->findOrFail($id);

        return response()->json(['transaction' => $row->raw]);
    }

    public function syncNow()
    {
        $lock = Cache::lock('veeqo_chargebacks_sync_now', 900);
        if (! $lock->get()) {
            return response()->json(['success' => false, 'message' => 'A chargeback sync is already running.'], 409);
        }
        try {
            @set_time_limit(900);
            $exit = Artisan::call('veeqo:sync-chargebacks', ['--days' => 30]);
            $lines = array_values(array_filter(explode("\n", trim(Artisan::output()))));

            return response()->json(['success' => $exit === 0, 'message' => end($lines) ?: 'Sync finished.'], $exit === 0 ? 200 : 502);
        } finally {
            $lock->release();
        }
    }

    private function filtered(Request $request): Builder
    {
        $query = VeeqoChargeback::query();

        $from = trim((string) $request->input('from', ''));
        $to = trim((string) $request->input('to', ''));
        if ($from !== '') {
            $query->where('posted_at', '>=', $from.' 00:00:00');
        }
        if ($to !== '') {
            $query->where('posted_at', '<=', $to.' 23:59:59');
        }
        foreach (['description' => 'type', 'direction' => 'direction', 'transaction_status' => 'status'] as $column => $param) {
            $value = trim((string) $request->input($param, ''));
            if ($value !== '') {
                $query->where($column, $value);
            }
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
            if (in_array($field, self::COLUMNS, true) && $value !== null && $value !== '') {
                $query->where($field, 'like', '%'.$value.'%');
            }
        }

        return $query;
    }

    /**
     * Veeqo shipment details for each Amazon order (an order can have several labels).
     *
     * @param  list<string>  $orderIds
     * @return array<string, array<string, mixed>>
     */
    private function shipmentsFor(array $orderIds): array
    {
        if ($orderIds === [] || ! Schema::hasTable('veeqo_billing_shipments')) {
            return [];
        }

        $out = [];
        DB::table('veeqo_billing_shipments')
            ->whereIn('order_number', $orderIds)
            ->orderBy('shipment_created_at')
            ->get(['order_number', 'shipment_created_at', 'tracking_number', 'sub_carrier_id', 'service_name', 'label_cost', 'shipment_weight', 'weight_unit', 'skus', 'channel_name', 'tracking_status'])
            ->each(function ($s) use (&$out) {
                $key = (string) $s->order_number;
                $row = $out[$key] ?? ['v_labels' => 0, 'v_label_cost' => 0.0, 'v_tracking' => [], 'v_carrier' => [], 'v_service' => [], 'v_skus' => [], 'v_status' => []];
                $row['v_labels']++;
                $row['v_shipped_at'] ??= $s->shipment_created_at ? substr((string) $s->shipment_created_at, 0, 16) : null;
                $row['v_label_cost'] = round($row['v_label_cost'] + (float) $s->label_cost, 2);
                $row['v_weight'] ??= $s->shipment_weight !== null ? rtrim(rtrim((string) $s->shipment_weight, '0'), '.').' '.$s->weight_unit : null;
                $row['v_channel'] ??= $s->channel_name;
                foreach (['v_tracking' => $s->tracking_number, 'v_carrier' => $s->sub_carrier_id, 'v_service' => $s->service_name, 'v_skus' => $s->skus, 'v_status' => $s->tracking_status] as $k => $v) {
                    if ($v !== null && $v !== '' && ! in_array($v, $row[$k], true)) {
                        $row[$k][] = $v;
                    }
                }
                $out[$key] = $row;
            });

        foreach ($out as $key => $row) {
            foreach (['v_tracking', 'v_carrier', 'v_service', 'v_skus', 'v_status'] as $k) {
                $out[$key][$k] = implode(', ', $row[$k]);
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function componentTypes(): array
    {
        return Cache::remember('veeqo_chargeback_component_types', 600, function () {
            $types = [];
            VeeqoChargeback::query()->select(['id', 'components'])->orderByDesc('id')->limit(20000)->get()
                ->each(function ($row) use (&$types) {
                    foreach (array_keys((array) $row->components) as $type) {
                        $types[$type] = true;
                    }
                });
            $types = array_keys($types);
            sort($types);

            return $types;
        });
    }

    /**
     * @return list<string>
     */
    private function distinct(string $column): array
    {
        return DB::table('veeqo_chargebacks')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn ($v) => (string) $v)
            ->all();
    }
}
