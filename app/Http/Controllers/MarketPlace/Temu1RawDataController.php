<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Temu 1 Raw Data — read-only Tabulator view of every table the Temu 1 API sync writes to,
 * with all columns shown exactly as stored (nothing is calculated or merged with other sources).
 */
class Temu1RawDataController extends Controller
{
    /** Cells longer than this are cut in the grid; the full value opens from the row's "View" link. */
    private const PREVIEW_CHARS = 160;

    private const MAX_PAGE_SIZE = 500;

    /**
     * @return array<string, array{label: string, table: string, api: string, order: string}>
     */
    private function sources(): array
    {
        return [
            'products' => [
                'label' => 'Products / SKUs',
                'table' => 'temu_metrics',
                'api' => 'bg.local.goods.list.query · bg.local.goods.sku.list.query · price query · analytics',
                'order' => 'id',
            ],
            'orders' => [
                'label' => 'Orders',
                'table' => 'temu_orders',
                'api' => 'bg.order.list.v2.get · bg.order.amount.query · tracking',
                'order' => 'parent_order_time',
            ],
            'ads' => [
                'label' => 'Ads Reports',
                'table' => 'temu_ads_api_reports',
                'api' => 'Temu Ads report API (L7 / L30 / L60)',
                'order' => 'id',
            ],
        ];
    }

    public function index()
    {
        $sources = [];
        foreach ($this->sources() as $key => $s) {
            $sources[$key] = [
                'label' => $s['label'],
                'api' => $s['api'],
                'table' => $s['table'],
                'exists' => Schema::hasTable($s['table']),
            ];
        }

        return view('market-places.temu1_raw_data_view', ['sources' => $sources]);
    }

    /**
     * Column definitions for one source, taken straight from the database so new API columns appear automatically.
     */
    public function columns(Request $request): JsonResponse
    {
        [$source, $table] = $this->resolveSource($request);
        $cols = $this->tableColumns($table);

        return response()->json([
            'source' => $source,
            'table' => $table,
            'columns' => array_values(array_map(fn ($c) => [
                'name' => $c['name'],
                'type' => $c['type'],
                'long' => $c['long'],
                'heavy' => $c['heavy'],
            ], $cols)),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        [$source, $table, $def] = $this->resolveSource($request);
        $cols = $this->tableColumns($table);
        $byName = [];
        foreach ($cols as $c) {
            $byName[$c['name']] = $c;
        }

        $size = max(1, min(self::MAX_PAGE_SIZE, (int) $request->query('size', 50)));
        $page = max(1, (int) $request->query('page', 1));

        $query = DB::table($table);

        // Global search across the short text columns + ids.
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($w) use ($cols, $like) {
                foreach ($cols as $c) {
                    if (! $c['heavy']) {
                        $w->orWhere($c['name'], 'like', $like);
                    }
                }
            });
        }

        // Per-column header filters (Tabulator remote filter: filter[i][field|type|value]).
        foreach ((array) $request->query('filter', []) as $f) {
            $field = (string) ($f['field'] ?? '');
            $value = trim((string) ($f['value'] ?? ''));
            if ($value === '' || ! isset($byName[$field]) || $byName[$field]['heavy']) {
                continue;
            }
            $query->where($field, 'like', '%'.addcslashes($value, '%_\\').'%');
        }

        $total = (clone $query)->count();

        $sorted = false;
        foreach ((array) $request->query('sort', []) as $s) {
            $field = (string) ($s['field'] ?? '');
            if (isset($byName[$field]) && ! $byName[$field]['heavy']) {
                $query->orderBy($field, strtolower((string) ($s['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc');
                $sorted = true;
            }
        }
        if (! $sorted) {
            $orderCol = isset($byName[$def['order']]) ? $def['order'] : ($cols[0]['name'] ?? 'id');
            $query->orderByDesc($orderCol);
            if ($orderCol !== 'id' && isset($byName['id'])) {
                $query->orderByDesc('id');
            }
        }

        // Long text / JSON columns come back as a preview only; the full value is fetched on demand.
        $select = [];
        foreach ($cols as $c) {
            $name = $c['name'];
            if ($c['long']) {
                $select[] = DB::raw('CHAR_LENGTH(`'.$name.'`) AS `'.$name.'__len`');
                $select[] = DB::raw('LEFT(`'.$name.'`, '.self::PREVIEW_CHARS.') AS `'.$name.'`');
            } else {
                $select[] = DB::raw('`'.$name.'`');
            }
        }

        $rows = $query->select($select)->forPage($page, $size)->get();

        return response()->json([
            'last_page' => max(1, (int) ceil($total / $size)),
            'total' => $total,
            'data' => $rows,
        ]);
    }

    /** Full, untruncated value of one cell (long text / JSON columns). */
    public function cell(Request $request): JsonResponse
    {
        [, $table] = $this->resolveSource($request);
        $names = array_column($this->tableColumns($table), 'name');
        $column = (string) $request->query('column', '');
        if (! in_array($column, $names, true)) {
            abort(404, 'Unknown column.');
        }

        $value = DB::table($table)->where('id', (int) $request->query('id'))->value($column);
        $pretty = null;
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return response()->json([
            'column' => $column,
            'value' => $pretty ?? (string) $value,
            'is_json' => $pretty !== null,
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: array}
     */
    private function resolveSource(Request $request): array
    {
        $sources = $this->sources();
        $source = (string) $request->query('source', 'products');
        abort_unless(isset($sources[$source]), 404, 'Unknown source.');
        $def = $sources[$source];
        abort_unless(Schema::hasTable($def['table']), 404, "Table {$def['table']} does not exist on this environment.");

        return [$source, $def['table'], $def];
    }

    /**
     * long  = shown as a preview in the grid, full value on demand (text / json / blob).
     * heavy = never searched, filtered or sorted in SQL (longtext / json / blob such as raw API payloads).
     *
     * @return list<array{name: string, type: string, long: bool, heavy: bool}>
     */
    private function tableColumns(string $table): array
    {
        $out = [];
        foreach (Schema::getColumns($table) as $col) {
            $type = strtolower((string) ($col['type_name'] ?? $col['type'] ?? ''));
            $long = (bool) preg_match('/text|json|blob/', $type);
            $out[] = [
                'name' => (string) $col['name'],
                'type' => $type,
                'long' => $long,
                'heavy' => (bool) preg_match('/^(longtext|mediumtext|json|.*blob)$/', $type),
            ];
        }

        return $out;
    }
}
