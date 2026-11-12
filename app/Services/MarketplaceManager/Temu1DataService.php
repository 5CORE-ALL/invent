<?php

namespace App\Services\MarketplaceManager;

use App\Services\TemuApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Temu 1 Data page: reads the category and the item specifics (goods properties) of a live Temu goods exactly as
 * Temu holds them, and sends edited item specifics back.
 *
 * Temu answers a goods with property entries that only carry ids (refPid / pid / templatePid, vid, value), so the
 * names come from the category template (temu.local.product.attributes.get + bg.local.goods.template.get).
 * Every answer is read defensively: Temu uses several key names for the same thing.
 */
class Temu1DataService
{
    public const SHOP = 'temu';

    /** Keys Temu accepts back on a property entry of an update. */
    private const ENTRY_KEYS = ['vid', 'specId', 'imgUrl', 'note', 'parentSpecId', 'valueUnitId', 'groupId', 'valueUnit',
        'templatePid', 'numberInputValue', 'value', 'refPid', 'pid'];

    public function __construct(private TemuApiService $api) {}

    public static function key(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $name) ?? $name);
    }

    // ---- Reading ----

    /**
     * The goods record (bg.local.goods.detail.query / temu.local.goods.detail.retrieve).
     *
     * @return array<string, mixed>
     */
    public function detail(string $goodsId): array
    {
        foreach (['temu.local.goods.detail.retrieve', 'bg.local.goods.detail.query'] as $type) {
            $res = $this->api->callOpenApi($type, ['goodsId' => (int) $goodsId], 45);
            if (($res['success'] ?? false) && is_array($res['result'] ?? null) && $res['result'] !== []) {
                return $res['result'];
            }
        }

        return [];
    }

    /**
     * The leaf category Temu holds for a goods, and its name when Temu gives one.
     *
     * @param  array<string, mixed>  $detail
     * @return array{id: int, name: string}
     */
    public function categoryOf(string $goodsId, array $detail): array
    {
        $id = 0;
        $name = trim((string) ($detail['catName'] ?? $detail['categoryName'] ?? ''));
        foreach (['leafCatId', 'catId', 'leafCategoryId', 'categoryId'] as $key) {
            if (is_numeric($detail[$key] ?? null) && (int) $detail[$key] > 0) {
                $id = (int) $detail[$key];
                break;
            }
        }
        if ($id > 0) {
            return ['id' => $id, 'name' => $name];
        }

        foreach ([1, 4, 5, 6] as $searchType) {
            $res = $this->api->callOpenApi('bg.local.goods.list.query', [
                'goodsSearchType' => $searchType,
                'goodsIdList' => [(int) $goodsId],
                'pageSize' => 5,
                'pageNumber' => 1,
                'pageNo' => 1,
                'language' => 'en',
            ], 30);
            foreach ((array) ($res['result']['goodsList'] ?? []) as $row) {
                if (is_array($row) && trim((string) ($row['goodsId'] ?? '')) === $goodsId && (int) ($row['catId'] ?? 0) > 0) {
                    return ['id' => (int) $row['catId'], 'name' => $name !== '' ? $name : trim((string) ($row['catName'] ?? ''))];
                }
            }
        }

        return ['id' => 0, 'name' => $name];
    }

    /**
     * The attributes of a Temu category, kept in a file for three days.
     *
     * @return list<array<string, mixed>> name, refPid, pid, templatePid, isSale, required, control, values [[vid, label]], units [[id, name]]
     */
    /** Why the last template call came back empty (shown to the user). */
    public string $lastError = '';

    private static function templateFile(int $catId): string
    {
        return 'temu_cache/temu1_tpl_'.$catId.'.json';
    }

    /**
     * What is kept for a category: 'ready' (attribute list stored), 'empty' (Temu gave none, tried less than a day ago)
     * or 'none' (never loaded, or too old). Reading never calls Temu.
     *
     * @return array{state: string, props: list<array<string, mixed>>}
     */
    public static function cachedTemplate(int $catId): array
    {
        try {
            $disk = Storage::disk('local');
            $file = self::templateFile($catId);
            if ($catId > 0 && $disk->exists($file)) {
                $cached = json_decode((string) $disk->get($file), true);
                if (is_array($cached)) {
                    $age = now()->timestamp - (int) ($cached['at'] ?? 0);
                    if (! empty($cached['props']) && $age < 14 * 86400) {
                        return ['state' => 'ready', 'props' => $cached['props']];
                    }
                    if (empty($cached['props']) && $age < 86400) {
                        return ['state' => 'empty', 'props' => []];
                    }
                }
            }
        } catch (\Throwable $e) {
            // no cache
        }

        return ['state' => 'none', 'props' => []];
    }

    public function template(int $catId, bool $fresh = false): array
    {
        $this->lastError = '';
        if ($catId <= 0) {
            return [];
        }
        $file = self::templateFile($catId);
        if (! $fresh) {
            $cached = self::cachedTemplate($catId);
            if ($cached['state'] === 'ready') {
                return $cached['props'];
            }
        }

        $list = [];
        // This shop's token is not allowed to call temu.local.product.attributes.get (3000032), so it is asked once a day
        // at most; bg.local.goods.template.get below carries the attribute list.
        $attrs = ['success' => false, 'message' => 'skipped (token has no access to this API)'];
        if (! Cache::has('temu1data.attrs_denied')) {
            $attrs = $this->api->callOpenApi('temu.local.product.attributes.get', ['catId' => $catId, 'language' => 'en'], 45);
            if (! ($attrs['success'] ?? false) && preg_match('/3000032|access_token|api access/i', (string) ($attrs['message'] ?? ''))) {
                Cache::put('temu1data.attrs_denied', true, now()->addDay());
            }
        }
        $result = is_array($attrs['result'] ?? null) ? $attrs['result'] : [];
        foreach (['attributeList', 'properties', 'propertyList', 'goodsProperties'] as $key) {
            if (is_array($result[$key] ?? null) && $result[$key] !== []) {
                $list = $result[$key];
                break;
            }
        }
        $tpl = $this->api->callOpenApi('bg.local.goods.template.get', ['catId' => $catId, 'language' => 'en'], 45);
        $more = $tpl['result']['templateInfo']['goodsProperties'] ?? $tpl['result']['goodsProperties'] ?? [];
        if (is_array($more) && $more !== []) {
            $list = array_merge($list, $more);
        }

        $byRef = [];
        foreach ($list as $prop) {
            if (! is_array($prop)) {
                continue;
            }
            $ref = (string) ($prop['refPid'] ?? $prop['pid'] ?? $prop['templatePid'] ?? '');
            if ($ref === '') {
                continue;
            }
            $byRef[$ref] = isset($byRef[$ref]) ? array_merge($byRef[$ref], $prop) : $prop;
        }

        $props = [];
        foreach ($byRef as $prop) {
            $props[] = $this->compact($prop);
        }
        if ($props === []) {
            Log::warning('Temu1Data: no category attributes returned', [
                'catId' => $catId,
                'attrs_error' => $attrs['message'] ?? '',
                'template_error' => $tpl['message'] ?? '',
                'attrs_result_keys' => array_keys($result),
            ]);
            $errors = array_values(array_filter([
                ! ($tpl['success'] ?? false) ? 'template: '.($tpl['message'] ?? 'failed') : '',
                ! ($attrs['success'] ?? false) && ! str_starts_with((string) ($attrs['message'] ?? ''), 'skipped') ? 'attributes: '.($attrs['message'] ?? 'failed') : '',
            ]));
            // Temu's own "internal system error" (4000000) is temporary: ask again in ten minutes, not tomorrow.
            $temporary = (bool) preg_match('/4000000|internal system error/i', (string) ($tpl['message'] ?? ''));
            $this->lastError = $errors !== []
                ? implode(' | ', $errors)
                : 'Temu returned no attributes for category '.$catId.' (answer keys: '.implode(',', array_keys($result)).').';
            try {
                // Remembered for a day so a category Temu cannot answer is not asked again on every page load.
                Storage::disk('local')->put($file, json_encode(['at' => now()->timestamp - ($temporary ? 86400 - 600 : 0), 'props' => []]));
            } catch (\Throwable $e) {
                // not remembered
            }

            return [];
        }
        try {
            Storage::disk('local')->put($file, json_encode(['at' => now()->timestamp, 'props' => $props], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            // not cached
        }

        return $props;
    }

    /**
     * Saves a category's attribute names as its columns (the channel's extra_columns table), so the fields stay with
     * the category instead of only with the SKU that was pulled. Names already stored are left as they are.
     *
     * @param  list<string>  $names
     */
    public function saveCategoryColumns(string $itemTable, string $path, array $names): void
    {
        $path = trim($path);
        $extra = preg_replace('/item_specifics$/', 'extra_columns', $itemTable);
        if ($path === '' || $names === [] || $extra === null || $extra === $itemTable || ! Schema::hasTable($extra)) {
            return;
        }
        $hash = sha1(mb_strtolower($path));
        $have = [];
        foreach (DB::table($extra)->where('category_hash', $hash)->pluck('name_key') as $key) {
            $have[(string) $key] = true;
        }
        $now = now();
        foreach ($names as $name) {
            $name = trim((string) $name);
            $key = self::key($name);
            if ($name === '' || $key === '' || isset($have[$key])) {
                continue;
            }
            try {
                DB::table($extra)->insert([
                    'category' => mb_substr($path, 0, 500),
                    'category_hash' => $hash,
                    'name' => mb_substr($name, 0, 191),
                    'name_key' => mb_substr($key, 0, 191),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (\Throwable $e) {
                // Another SKU of this category saved the same column a moment earlier.
            }
            $have[$key] = true;
        }
    }

    /**
     * The item-specific columns of a category: every attribute that is not a variation option.
     *
     * @param  list<array<string, mixed>>  $tpl
     * @return list<string>
     */
    public static function columnNames(array $tpl): array
    {
        $names = [];
        foreach ($tpl as $prop) {
            $name = (string) ($prop['name'] ?? '');
            if ($name !== '' && empty($prop['isSale']) && ! preg_match('/\b(upc|gtin|ean|isbn|barcode)\b/i', $name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $prop
     * @return array<string, mixed>
     */
    private function compact(array $prop): array
    {
        $values = [];
        foreach ($this->valueRows($prop) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $vid = (int) ($row['vid'] ?? $row['id'] ?? 0);
            $label = trim((string) ($row['value'] ?? $row['specName'] ?? $row['name'] ?? $row['attributeValue'] ?? ''));
            if ($vid > 0 && $label !== '') {
                $values[] = [$vid, $label];
            }
        }
        $units = [];
        foreach ((array) ($prop['valueUnitList'] ?? $prop['attributeValueUnitList'] ?? []) as $unit) {
            if (is_array($unit) && isset($unit['valueUnitId'])) {
                $units[] = [(int) $unit['valueUnitId'], trim((string) ($unit['valueUnit'] ?? $unit['name'] ?? ''))];
            }
        }

        return [
            'name' => trim((string) ($prop['attributeName'] ?? $prop['name'] ?? $prop['propName'] ?? $prop['propertyName'] ?? '')),
            'refPid' => (int) ($prop['refPid'] ?? 0),
            'pid' => (int) ($prop['pid'] ?? 0),
            'templatePid' => (int) ($prop['templatePid'] ?? 0),
            'isSale' => ! empty($prop['isSale']),
            'required' => (bool) ($prop['required'] ?? $prop['isRequired'] ?? false),
            'control' => (int) ($prop['controlType'] ?? $prop['attributeRules']['controlType'] ?? 1),
            'values' => $values,
            'units' => $units,
        ];
    }

    /**
     * @param  array<string, mixed>  $prop
     * @return list<mixed>
     */
    private function valueRows(array $prop): array
    {
        foreach (['values', 'valueList', 'vidList'] as $key) {
            if (isset($prop[$key]) && is_array($prop[$key]) && $prop[$key] !== []) {
                return array_values($prop[$key]);
            }
        }
        $out = [];
        foreach ((array) ($prop['attributeValueDetail'] ?? []) as $detail) {
            if (! is_array($detail)) {
                continue;
            }
            foreach ((array) ($detail['attributeValueList'] ?? []) as $value) {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * The template attribute a goods property entry belongs to.
     *
     * @param  list<array<string, mixed>>  $tpl
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    public function attributeOf(array $tpl, array $entry): ?array
    {
        foreach (['refPid', 'pid', 'templatePid'] as $key) {
            $id = (int) ($entry[$key] ?? 0);
            if ($id <= 0) {
                continue;
            }
            foreach ($tpl as $prop) {
                foreach (['refPid', 'pid', 'templatePid'] as $propKey) {
                    if ((int) ($prop[$propKey] ?? 0) === $id) {
                        return $prop;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return list<array<string, mixed>>
     */
    private function propertyEntries(array $detail): array
    {
        $list = $detail['goodsProperties'] ?? $detail['goodsProperty']['goodsProperties'] ?? [];
        $out = [];
        foreach ((array) $list as $entry) {
            if (is_array($entry)) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * Reads one goods from Temu and stores its category and item specifics.
     *
     * @return array{ok: bool, message: string, category: string, properties: int}
     */
    public function pull(string $table, string $sku, string $goodsId, array $keepKeys = []): array
    {
        $detail = $this->detail($goodsId);
        if ($detail === []) {
            return ['ok' => false, 'message' => 'Temu did not return the product (goods ID '.$goodsId.').', 'category' => '', 'properties' => 0];
        }
        $cat = $this->categoryOf($goodsId, $detail);
        $tpl = $this->template($cat['id']);

        $path = $cat['id'] > 0 ? TemuCategoryTree::path(self::SHOP, (string) $cat['id']) : '';
        if ($path === '') {
            $path = $cat['name'];
        }
        if ($path === '' && $cat['id'] > 0) {
            $path = 'Category '.$cat['id'];
        }

        $specifics = [];
        // Every attribute of the category is a column, blank when the goods has no value for it.
        foreach ($tpl as $prop) {
            if ($prop['name'] !== '' && ! $prop['isSale'] && ! preg_match('/\b(upc|gtin|ean|isbn|barcode)\b/i', $prop['name'])) {
                $specifics[$prop['name']] = '';
            }
        }
        $unknown = 0;
        foreach ($this->propertyEntries($detail) as $entry) {
            if (! empty($entry['specId']) || ! empty($entry['parentSpecId'])) {
                continue; // a variation spec (Color, Size), not an item specific
            }
            $attr = $this->attributeOf($tpl, $entry);
            if ($attr !== null && $attr['isSale']) {
                continue;
            }
            $name = $attr !== null ? (string) $attr['name'] : trim((string) ($entry['propName'] ?? $entry['name'] ?? $entry['attributeName'] ?? ''));
            if ($name === '') {
                $unknown++;

                continue;
            }
            $value = $this->entryText($attr, $entry);
            if ($value === '') {
                continue;
            }
            $specifics[$name] = ($specifics[$name] ?? '') !== '' ? $specifics[$name].', '.$value : $value;
        }
        if ($unknown > 0) {
            Log::info('Temu1Data: property entries without a name', ['sku' => $sku, 'goods_id' => $goodsId, 'count' => $unknown, 'cat' => $cat['id']]);
        }
        if ($tpl === [] && $specifics === []) {
            Log::warning('Temu1Data: nothing readable', ['sku' => $sku, 'goods_id' => $goodsId, 'cat' => $cat['id'], 'keys' => array_keys($detail)]);
        }

        $key = strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
        $stored = DB::table($table)->where('sku', $key)->first();
        $old = $stored ? json_decode((string) $stored->specifics, true) : [];
        $old = is_array($old) ? $old : [];
        // Edits that are not on Temu yet are kept, and so are the title and price waiting for Push.
        foreach ($old as $name => $value) {
            $oldKey = self::key((string) $name);
            if (str_starts_with((string) $name, '__ebay_')) {
                $specifics[$name] = $value;
            } elseif (isset($keepKeys[$oldKey])) {
                foreach (array_keys($specifics) as $existing) {
                    if (self::key((string) $existing) === $oldKey) {
                        unset($specifics[$existing]);
                    }
                }
                $specifics[$name] = $value;
            }
        }
        $specifics['__goods_name'] = trim((string) ($detail['goodsName'] ?? ''));

        $payload = [
            'item_id' => $goodsId,
            'category_id' => $cat['id'] > 0 ? (string) $cat['id'] : null,
            'category_path' => $path !== '' ? mb_substr($path, 0, 255) : null,
            'specifics' => json_encode($specifics, JSON_UNESCAPED_UNICODE),
            'pulled_at' => now(),
            'updated_at' => now(),
        ];
        if ($stored) {
            DB::table($table)->where('sku', $key)->update($payload);
        } else {
            DB::table($table)->insert($payload + ['sku' => $key, 'created_at' => now()]);
        }

        // The category keeps the field list, so every SKU of it shows these columns, not only the one just pulled.
        if ($tpl !== []) {
            $this->saveCategoryColumns($table, $path, self::columnNames($tpl));
        }

        $filled = count(array_filter($specifics, static fn ($v, $k) => ! str_starts_with((string) $k, '__') && trim((string) $v) !== '', ARRAY_FILTER_USE_BOTH));

        return [
            'ok' => true,
            'message' => ($path !== '' ? $path : 'No category').': '.$filled.' item specific'.($filled === 1 ? '' : 's')
                .($tpl === [] ? ' (the category attribute list could not be read, so only the names Temu returned are shown)' : ''),
            'category' => $path,
            'properties' => $filled,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $attr
     * @param  array<string, mixed>  $entry
     */
    private function entryText(?array $attr, array $entry): string
    {
        $vid = (int) ($entry['vid'] ?? 0);
        $typed = trim((string) ($entry['value'] ?? ''));
        if ($typed !== '') {
            return $typed;
        }
        if ($vid > 0 && $attr !== null) {
            foreach ($attr['values'] as [$valueId, $label]) {
                if ((int) $valueId === $vid) {
                    return (string) $label;
                }
            }
        }
        $number = $entry['numberInputValue'] ?? null;
        if ($number !== null && $number !== '') {
            $unit = trim((string) ($entry['valueUnit'] ?? ''));
            if ($unit === '' && $attr !== null && ! empty($entry['valueUnitId'])) {
                foreach ($attr['units'] as [$unitId, $unitName]) {
                    if ((int) $unitId === (int) $entry['valueUnitId']) {
                        $unit = (string) $unitName;
                    }
                }
            }

            return trim((string) $number.' '.$unit);
        }

        return '';
    }

    // ---- Writing (test: only on the SKUs the user ticks) ----

    /**
     * Sends edited item specifics of one goods with bg.local.goods.partial.update, then reads the goods back.
     * Every property Temu already holds is sent again unchanged, so only the edited ones differ.
     *
     * @param  array<string, string>  $edits  attribute name => value
     * @return array{success: bool, message: string, applied: list<string>}
     */
    public function pushProperties(string $goodsId, array $edits): array
    {
        $detail = $this->detail($goodsId);
        if ($detail === []) {
            return ['success' => false, 'message' => 'Temu did not return the product, so nothing was sent.', 'applied' => []];
        }
        $cat = $this->categoryOf($goodsId, $detail);
        $tpl = $this->template($cat['id']);
        if ($tpl === []) {
            return ['success' => false, 'message' => 'The Temu attribute list of this category could not be read, so nothing was sent.', 'applied' => []];
        }

        $byKey = [];
        foreach ($tpl as $prop) {
            if ($prop['name'] !== '') {
                $byKey[self::key($prop['name'])] = $prop;
            }
        }

        // What Temu holds now, entry by entry.
        $current = [];
        foreach ($this->propertyEntries($detail) as $entry) {
            $kept = array_intersect_key($entry, array_flip(self::ENTRY_KEYS));
            foreach ($kept as $k => $v) {
                if ($v === null || $v === '') {
                    unset($kept[$k]);
                }
            }
            if ($kept !== []) {
                $current[] = $kept;
            }
        }

        $applied = [];
        $problems = [];
        $expect = [];
        foreach ($edits as $name => $value) {
            $prop = $byKey[self::key((string) $name)] ?? null;
            $value = trim((string) $value);
            if ($prop === null) {
                $problems[] = '"'.$name.'" is not an attribute of this Temu category';

                continue;
            }
            if ($prop['isSale']) {
                $problems[] = '"'.$name.'" is a variation option, not an item specific';

                continue;
            }
            if ($value === '') {
                continue;
            }
            $entry = [];
            foreach (['refPid', 'pid', 'templatePid'] as $idKey) {
                if ($prop[$idKey] > 0) {
                    $entry[$idKey] = $prop[$idKey];
                }
            }
            if ($prop['values'] !== [] && ! in_array($prop['control'], [0, 16, 19], true)) {
                $vid = null;
                foreach ($prop['values'] as [$valueId, $label]) {
                    if (mb_strtolower(trim((string) $label)) === mb_strtolower($value)) {
                        $vid = (int) $valueId;
                        $value = (string) $label;
                        break;
                    }
                }
                if ($vid === null) {
                    $sample = implode(', ', array_slice(array_map(static fn ($r) => (string) $r[1], $prop['values']), 0, 8));
                    $problems[] = '"'.$value.'" is not one of the values Temu allows for '.$name.' (for example: '.$sample.')';

                    continue;
                }
                $entry['vid'] = $vid;
                $entry['value'] = $value;
            } else {
                $entry['value'] = $value;
                if ($prop['units'] !== []) {
                    $entry['valueUnitId'] = (int) $prop['units'][0][0];
                }
            }

            // Same attribute already on the goods: replaced, not added twice.
            $current = array_values(array_filter($current, static function (array $have) use ($entry): bool {
                foreach (['refPid', 'pid', 'templatePid'] as $idKey) {
                    if (! empty($entry[$idKey]) && (int) ($have[$idKey] ?? 0) === (int) $entry[$idKey]) {
                        return false;
                    }
                }

                return true;
            }));
            $current[] = $entry;
            $applied[] = (string) $name;
            $expect[(string) $name] = ['prop' => $prop, 'value' => $value];
        }

        if ($applied === []) {
            return [
                'success' => false,
                'message' => $problems !== [] ? implode('; ', $problems).'.' : 'No item specific values to send.',
                'applied' => [],
            ];
        }
        $notes = $problems !== [] ? ' Not sent: '.implode('; ', $problems).'.' : '';

        $res = $this->api->callOpenApi('bg.local.goods.partial.update', [
            'goodsId' => (int) $goodsId,
            'language' => 'en',
            'goodsProperty' => ['goodsProperties' => $current],
        ], 60);
        if (! ($res['success'] ?? false)) {
            Log::warning('Temu1Data: property update rejected', ['goods_id' => $goodsId, 'error' => $res['message'] ?? '', 'sent' => $applied]);

            return ['success' => false, 'message' => 'Temu rejected the item specifics: '.trim((string) ($res['message'] ?? $res['errorMsg'] ?? 'unknown error')), 'applied' => []];
        }

        // Temu answers success even when it drops a field, so the goods is read back.
        foreach ([2, 4] as $wait) {
            sleep($wait);
            $after = $this->detail($goodsId);
            if ($after === []) {
                break;
            }
            $missing = [];
            foreach ($expect as $name => $info) {
                $found = false;
                foreach ($this->propertyEntries($after) as $entry) {
                    $attr = $this->attributeOf($tpl, $entry);
                    if ($attr !== null && $attr['name'] === $info['prop']['name']
                        && mb_strtolower($this->entryText($attr, $entry)) === mb_strtolower($info['value'])) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $missing[] = $name;
                }
            }
            if ($missing === []) {
                return ['success' => true, 'message' => 'Item specifics updated on Temu ('.implode(', ', $applied).').'.$notes, 'applied' => $applied];
            }
        }
        if (isset($missing) && $missing !== []) {
            return [
                'success' => false,
                'message' => 'Temu accepted the update but the live product still shows the old value of '.implode(', ', $missing)
                    .'. It may be waiting for Temu review, or Temu ignores this field in a partial update.',
                'applied' => [],
            ];
        }

        return ['success' => true, 'message' => 'Item specifics sent to Temu ('.implode(', ', $applied).'); the live product could not be read back.'.$notes, 'applied' => $applied];
    }

    public static function tableReady(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
