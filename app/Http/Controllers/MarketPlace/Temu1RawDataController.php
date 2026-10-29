<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Services\TemuApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Temu 1 Raw Data — read-only Tabulator view of what the Temu 1 Open API returns.
 *
 *  - orders / ads : stored API responses (raw_json, amount_raw_json, raw_response) are decoded and every field
 *                   is flattened into its own column (api.*, amount.*), next to the table's own columns.
 *  - api_goods / api_skus : live Temu calls (bg.local.goods.list.query / bg.local.goods.sku.list.query),
 *                   every returned field as a column. Cached for a few minutes; "Refresh from API" re-pulls.
 *  - products : temu_metrics as stored by the sync.
 */
class Temu1RawDataController extends Controller
{
    /** Cells longer than this are cut in the grid; the full value opens from the cell's "View" link. */
    private const PREVIEW_CHARS = 160;

    private const MAX_PAGE_SIZE = 500;

    private const COLUMN_SAMPLE_ROWS = 300;

    private const LIVE_CACHE_MINUTES = 15;

    private const LIVE_PAGE_SIZE = 100;

    private const LIVE_MAX_PAGES = 200;

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sources(): array
    {
        return [
            'orders' => [
                'kind' => 'db',
                'label' => 'Orders (API)',
                'table' => 'temu_orders',
                'api' => 'bg.order.list.v2.get (raw_json) + bg.order.amount.query (amount_raw_json) — every field flattened',
                'order' => 'parent_order_time',
                // raw column => prefix for its flattened fields
                'flatten' => ['raw_json' => 'api', 'amount_raw_json' => 'amount'],
            ],
            'ads' => [
                'kind' => 'db',
                'label' => 'Ads Reports (API)',
                'table' => 'temu_ads_api_reports',
                'api' => 'Temu Ads report API, stored raw_response — every field flattened',
                'order' => 'id',
                'flatten' => ['raw_response' => 'api'],
            ],
            'api_goods' => [
                'kind' => 'live',
                'label' => 'Goods (live API)',
                'api' => 'bg.local.goods.list.query — live call, every field returned',
                'type' => 'bg.local.goods.list.query',
                'params' => ['goodsSearchType' => 1, 'goodsStatusFilterType' => 1],
                'list_key' => 'goodsList',
            ],
            'api_skus' => [
                'kind' => 'live',
                'label' => 'SKUs (live API)',
                'api' => 'bg.local.goods.sku.list.query — live call, every field returned',
                'type' => 'bg.local.goods.sku.list.query',
                'params' => ['skuSearchType' => 2],
                'list_key' => 'skuList',
            ],
            'products' => [
                'kind' => 'db',
                'label' => 'Products (stored)',
                'table' => 'temu_metrics',
                'api' => 'temu_metrics as written by the sync (goods list, price and analytics APIs)',
                'order' => 'id',
                'flatten' => [],
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
                'live' => $s['kind'] === 'live',
                'table' => $s['table'] ?? null,
                'exists' => $s['kind'] === 'live' || Schema::hasTable($s['table']),
            ];
        }

        return view('market-places.temu1_raw_data_view', ['sources' => $sources]);
    }

    /* ------------------------------------------------------------------ */
    /*  Columns                                                           */
    /* ------------------------------------------------------------------ */

    public function columns(Request $request): JsonResponse
    {
        [$source, $def] = $this->resolveSource($request);

        if ($def['kind'] === 'live') {
            $live = $this->loadLive($source, $def, $request->boolean('refresh'));
            if ($live['error'] !== null) {
                return response()->json(['message' => $live['error']], 422);
            }

            return response()->json([
                'source' => $source,
                'live' => true,
                'fetched_at' => $live['fetched_at'],
                'columns' => $this->liveColumns($live['rows']),
            ]);
        }

        return response()->json([
            'source' => $source,
            'live' => false,
            'columns' => $this->dbColumns($source, $def),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dbColumns(string $source, array $def): array
    {
        $out = [];
        foreach ($this->tableColumns($def['table']) as $c) {
            // The raw API payload columns are replaced by their flattened fields (kept as one View cell too).
            $out[] = [
                'name' => $c['name'],
                'type' => $c['type'],
                'long' => $c['long'],
                'heavy' => $c['heavy'],
                'kind' => 'db',
                'sortable' => ! $c['heavy'],
            ];
        }

        foreach ($this->discoverApiFields($source, $def) as $name => $isJson) {
            $out[] = [
                'name' => $name,
                'type' => 'api',
                'long' => $isJson,
                'heavy' => true,
                'kind' => 'api',
                'sortable' => false,
            ];
        }

        return $out;
    }

    /**
     * Union of flattened API field names over the most recent stored responses.
     *
     * @return array<string, bool> field name => whether values are JSON blobs
     */
    private function discoverApiFields(string $source, array $def): array
    {
        $flatten = $def['flatten'] ?? [];
        if ($flatten === []) {
            return [];
        }

        return Cache::remember('temu1_raw_cols:'.$source, now()->addMinutes(10), function () use ($def, $flatten) {
            $select = array_merge(['id'], array_keys($flatten));
            if (Schema::hasColumn($def['table'], 'order_sn')) {
                $select[] = 'order_sn';
            }

            $rows = DB::table($def['table'])
                ->select(array_unique($select))
                ->orderByDesc('id')
                ->limit(self::COLUMN_SAMPLE_ROWS)
                ->get();

            $fields = [];
            foreach ($rows as $row) {
                foreach ($this->flattenRow((array) $row, $flatten) as $name => $value) {
                    $fields[$name] = ($fields[$name] ?? false) || $this->looksLikeJson($value);
                }
            }

            return $fields;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function liveColumns(array $rows): array
    {
        $fields = [];
        foreach ($rows as $row) {
            foreach ($row as $name => $value) {
                $fields[$name] = ($fields[$name] ?? false) || $this->looksLikeJson($value);
            }
        }

        $out = [];
        foreach ($fields as $name => $isJson) {
            $out[] = [
                'name' => $name,
                'type' => 'api',
                'long' => $isJson,
                'heavy' => false,
                'kind' => 'api',
                'sortable' => true,
            ];
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /*  Data                                                              */
    /* ------------------------------------------------------------------ */

    public function data(Request $request): JsonResponse
    {
        [$source, $def] = $this->resolveSource($request);

        $size = max(1, min(self::MAX_PAGE_SIZE, (int) $request->query('size', 50)));
        $page = max(1, (int) $request->query('page', 1));

        return $def['kind'] === 'live'
            ? $this->liveData($request, $source, $def, $page, $size)
            : $this->dbData($request, $def, $page, $size);
    }

    private function dbData(Request $request, array $def, int $page, int $size): JsonResponse
    {
        $table = $def['table'];
        $flatten = $def['flatten'] ?? [];
        $cols = $this->tableColumns($table);
        $byName = [];
        foreach ($cols as $c) {
            $byName[$c['name']] = $c;
        }

        $query = DB::table($table);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $rawCols = array_keys($flatten);
            $query->where(function ($w) use ($cols, $like, $rawCols) {
                foreach ($cols as $c) {
                    // Raw API payloads are searched too (that is where most of the fields live).
                    if (! $c['heavy'] || in_array($c['name'], $rawCols, true)) {
                        $w->orWhere($c['name'], 'like', $like);
                    }
                }
            });
        }

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

        if ($flatten === []) {
            // No API payload to decode: long cells come back as a preview only (full value on demand).
            $select = [];
            foreach ($cols as $c) {
                $n = $c['name'];
                if ($c['long']) {
                    $select[] = DB::raw('CHAR_LENGTH(`'.$n.'`) AS `'.$n.'__len`');
                    $select[] = DB::raw('LEFT(`'.$n.'`, '.self::PREVIEW_CHARS.') AS `'.$n.'`');
                } else {
                    $select[] = DB::raw('`'.$n.'`');
                }
            }
            $rows = $query->select($select)->forPage($page, $size)->get()->map(fn ($r) => (array) $r)->all();
        } else {
            $rows = [];
            foreach ($query->forPage($page, $size)->get() as $row) {
                $row = (array) $row;
                $out = $row + $this->flattenRow($row, $flatten);
                // Keep the raw payload columns as preview + View, like every other long cell.
                foreach ($cols as $c) {
                    if ($c['long']) {
                        $full = $row[$c['name']] ?? null;
                        $out[$c['name'].'__len'] = $full === null ? 0 : mb_strlen((string) $full);
                        $out[$c['name']] = $full === null ? null : mb_substr((string) $full, 0, self::PREVIEW_CHARS);
                    }
                }
                $rows[] = $out;
            }
        }

        return response()->json([
            'last_page' => max(1, (int) ceil($total / $size)),
            'last_row' => $total,
            'total' => $total,
            'data' => $rows,
        ]);
    }

    private function liveData(Request $request, string $source, array $def, int $page, int $size): JsonResponse
    {
        $live = $this->loadLive($source, $def, $request->boolean('refresh'));
        if ($live['error'] !== null) {
            return response()->json(['message' => $live['error']], 422);
        }

        $rows = $live['rows'];

        $search = mb_strtolower(trim((string) $request->query('q', '')));
        if ($search !== '') {
            $rows = array_values(array_filter($rows, static function ($row) use ($search) {
                foreach ($row as $v) {
                    if ($v !== null && str_contains(mb_strtolower((string) $v), $search)) {
                        return true;
                    }
                }

                return false;
            }));
        }

        foreach ((array) $request->query('filter', []) as $f) {
            $field = (string) ($f['field'] ?? '');
            $value = mb_strtolower(trim((string) ($f['value'] ?? '')));
            if ($field === '' || $value === '') {
                continue;
            }
            $rows = array_values(array_filter($rows, static fn ($row) => str_contains(mb_strtolower((string) ($row[$field] ?? '')), $value)));
        }

        foreach (array_reverse((array) $request->query('sort', [])) as $s) {
            $field = (string) ($s['field'] ?? '');
            if ($field === '') {
                continue;
            }
            $desc = strtolower((string) ($s['dir'] ?? 'asc')) === 'desc';
            usort($rows, static function ($a, $b) use ($field, $desc) {
                $x = $a[$field] ?? null;
                $y = $b[$field] ?? null;
                $cmp = (is_numeric($x) && is_numeric($y)) ? ($x <=> $y) : strnatcasecmp((string) $x, (string) $y);

                return $desc ? -$cmp : $cmp;
            });
        }

        $total = count($rows);

        return response()->json([
            'last_page' => max(1, (int) ceil($total / $size)),
            'last_row' => $total,
            'total' => $total,
            'fetched_at' => $live['fetched_at'],
            'data' => array_slice($rows, ($page - 1) * $size, $size),
        ]);
    }

    /**
     * Full, untruncated value of one stored cell (long text / JSON columns).
     */
    public function cell(Request $request): JsonResponse
    {
        [, $def] = $this->resolveSource($request);
        abort_if($def['kind'] === 'live', 404);

        $names = array_column($this->tableColumns($def['table']), 'name');
        $column = (string) $request->query('column', '');
        if (! in_array($column, $names, true)) {
            abort(404, 'Unknown column.');
        }

        $value = DB::table($def['table'])->where('id', (int) $request->query('id'))->value($column);
        $pretty = null;
        if (is_string($value) && $value !== '') {
            $decoded = $this->decodeJson($value);
            if (is_array($decoded)) {
                $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return response()->json([
            'column' => $column,
            'value' => $pretty ?? (string) $value,
            'is_json' => $pretty !== null,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Live Temu API                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{rows: list<array<string, mixed>>, fetched_at: ?string, error: ?string}
     */
    private function loadLive(string $source, array $def, bool $refresh): array
    {
        $key = 'temu1_raw_live:'.$source;
        if ($refresh) {
            Cache::forget($key);
        }

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached + ['error' => null];
        }

        /** @var TemuApiService $temu */
        $temu = app(TemuApiService::class);
        if (! $temu->isConfigured()) {
            return ['rows' => [], 'fetched_at' => null, 'error' => 'Temu API credentials are not configured on this environment — open this tab on the server.'];
        }

        @set_time_limit(300);

        $rows = [];
        for ($pageNo = 1; $pageNo <= self::LIVE_MAX_PAGES; $pageNo++) {
            $resp = $temu->callOpenApi($def['type'], $def['params'] + [
                'pageSize' => self::LIVE_PAGE_SIZE,
                'pageNumber' => $pageNo,
            ]);

            if (! ($resp['success'] ?? false)) {
                return ['rows' => [], 'fetched_at' => null, 'error' => 'Temu API error: '.($resp['message'] ?? $resp['errorMsg'] ?? 'unknown')];
            }

            $result = is_array($resp['result'] ?? null) ? $resp['result'] : [];
            $items = $result[$def['list_key']] ?? [];
            if (! is_array($items) || $items === []) {
                break;
            }

            foreach ($items as $item) {
                $rows[] = is_array($item) ? $this->flatten($item) : ['value' => $item];
            }

            $total = (int) ($result['total'] ?? 0);
            if ($total > 0 && count($rows) >= $total) {
                break;
            }
        }

        $payload = ['rows' => $rows, 'fetched_at' => now()->toDateTimeString()];
        Cache::put($key, $payload, now()->addMinutes(self::LIVE_CACHE_MINUTES));

        return $payload + ['error' => null];
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function resolveSource(Request $request): array
    {
        $sources = $this->sources();
        $source = (string) $request->query('source', 'orders');
        abort_unless(isset($sources[$source]), 404, 'Unknown source.');
        $def = $sources[$source];
        if ($def['kind'] === 'db') {
            abort_unless(Schema::hasTable($def['table']), 404, "Table {$def['table']} does not exist on this environment.");
        }

        return [$source, $def];
    }

    /**
     * Decode and flatten the stored API payload columns of one row.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $flatten  raw column => prefix
     * @return array<string, mixed>
     */
    private function flattenRow(array $row, array $flatten): array
    {
        $out = [];
        foreach ($flatten as $column => $prefix) {
            $decoded = $this->decodeJson($row[$column] ?? null);
            if (! is_array($decoded)) {
                continue;
            }

            // The amount payload lists every order of the parent; keep only this row's line.
            if (isset($decoded['orderList']) && is_array($decoded['orderList']) && array_is_list($decoded['orderList'])) {
                $mine = null;
                foreach ($decoded['orderList'] as $line) {
                    if (is_array($line) && ($line['orderSn'] ?? null) === ($row['order_sn'] ?? null)) {
                        $mine = $line;
                        break;
                    }
                }
                $decoded['orderList'] = $mine ?? ($decoded['orderList'][0] ?? []);
            }

            foreach ($this->flatten($decoded, $prefix) as $name => $value) {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    /**
     * Flatten nested arrays into dotted field names. Lists of scalars are comma-joined,
     * [{name, value}] lists become "name=value; ...", any other list is kept as a JSON string.
     *
     * @return array<string, mixed>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                if ($value === []) {
                    $out[$name] = null;
                } elseif (! array_is_list($value)) {
                    $out += $this->flatten($value, $name);
                } elseif ($this->isScalarList($value)) {
                    $out[$name] = implode(', ', array_map(static fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v, $value));
                } elseif ($this->isNameValueList($value)) {
                    $out[$name] = implode('; ', array_map(static fn ($v) => $v['name'].'='.(is_scalar($v['value']) ? (string) $v['value'] : json_encode($v['value'])), $value));
                } else {
                    $out[$name] = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            } elseif (is_bool($value)) {
                $out[$name] = $value ? 'true' : 'false';
            } else {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    private function isScalarList(array $list): bool
    {
        foreach ($list as $v) {
            if (! is_scalar($v) && $v !== null) {
                return false;
            }
        }

        return true;
    }

    private function isNameValueList(array $list): bool
    {
        foreach ($list as $v) {
            if (! is_array($v) || count($v) !== 2 || ! isset($v['name']) || ! array_key_exists('value', $v) || ! is_scalar($v['name'])) {
                return false;
            }
        }

        return true;
    }

    private function looksLikeJson(mixed $value): bool
    {
        return is_string($value) && $value !== '' && ($value[0] === '[' || $value[0] === '{') && strlen($value) > 2;
    }

    /**
     * Some payloads were stored double-encoded (a JSON string holding JSON), so decode up to twice.
     */
    private function decodeJson(mixed $value): mixed
    {
        for ($i = 0; $i < 3 && is_string($value) && $value !== ''; $i++) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }
            $value = $decoded;
        }

        return $value;
    }

    /**
     * long  = shown as a preview in the grid, full value on demand (text / json / blob).
     * heavy = never filtered or sorted in SQL (longtext / json / blob such as raw API payloads).
     *
     * @return list<array{name: string, type: string, long: bool, heavy: bool}>
     */
    private function tableColumns(string $table): array
    {
        $out = [];
        foreach (Schema::getColumns($table) as $col) {
            $type = strtolower((string) ($col['type_name'] ?? $col['type'] ?? ''));
            $out[] = [
                'name' => (string) $col['name'],
                'type' => $type,
                'long' => (bool) preg_match('/text|json|blob/', $type),
                'heavy' => (bool) preg_match('/^(longtext|mediumtext|json|.*blob)$/', $type),
            ];
        }

        return $out;
    }
}
