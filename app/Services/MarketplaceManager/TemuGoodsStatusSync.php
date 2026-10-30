<?php

namespace App\Services\MarketplaceManager;

use App\Models\ShopifySku;
use App\Services\TemuApiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Temu 1 only: the status Seller Center shows for every goods / SKU (Active, Inactive, Incomplete, Draft,
 * Deleted), so the listing page can show what is really there instead of just "Missing L".
 *
 * Read-only against Temu. Result is stored in storage/app/temu_cache/temu1_goods_status.json.
 */
class TemuGoodsStatusSync
{
    public const FILE = 'temu_cache/temu1_goods_status.json';

    /** Seller Center tabs, lowest priority first (a later tab wins when a goods appears in two). */
    private const TABS = [
        'ACTIVE' => 'Active',
        'INACTIVE' => 'Inactive',
        'INCOMPLETE' => 'Incomplete',
        'DRAFT' => 'Draft',
        'DELETED' => 'Deleted',
    ];

    private const RANK = ['Active' => 6, 'Inactive' => 5, 'Incomplete' => 4, 'Draft' => 3, 'Deleted' => 1];

    /** @var array<string, mixed>|null|false */
    private static $memo = false;

    public function __construct(protected TemuApiService $api) {}

    /**
     * @return array{ok: bool, goods: int, skus: int, counts: array<string, int>, tabs: array<string, string>, note: string}
     */
    public function refresh(): array
    {
        $out = ['ok' => false, 'goods' => 0, 'skus' => 0, 'counts' => [], 'tabs' => [], 'note' => ''];
        if (! $this->api->isConfigured()) {
            $out['note'] = 'Temu credentials are not configured.';

            return $out;
        }

        // 1) Everything Temu lists, status derived from the goods record itself.
        $all = $this->pass('ALL');
        if ($all === null) {
            $out['note'] = 'Temu goods list could not be read - status not refreshed.';

            return $out;
        }
        $goods = [];
        foreach ($all as $g) {
            $gid = (string) $g['goodsId'];
            $goods[$gid] = [
                'status' => $this->labelFromRecord($g),
                'raw' => $this->rawStatusFields($g),
                'name' => (string) ($g['goodsName'] ?? ''),
                'skus' => $this->skusOf($g),
            ];
        }

        // 2) Seller Center tabs are the authority: what Temu shows under Incomplete / Draft / ... wins.
        foreach (self::TABS as $searchType => $label) {
            $rows = $this->pass($searchType);
            if ($rows === null) {
                $out['tabs'][$label] = 'not available';
                continue;
            }
            $out['tabs'][$label] = (string) count($rows);
            foreach ($rows as $g) {
                $gid = (string) $g['goodsId'];
                $goods[$gid] = [
                    'status' => $label,
                    'raw' => $this->rawStatusFields($g) ?: ($goods[$gid]['raw'] ?? []),
                    'name' => (string) ($g['goodsName'] ?? ($goods[$gid]['name'] ?? '')),
                    'skus' => $this->skusOf($g) ?: ($goods[$gid]['skus'] ?? []),
                ];
            }
        }

        // 3) SKU -> goods (a SKU kept on a deleted goods and relisted: the live one wins).
        $skus = [];
        foreach ($goods as $gid => $info) {
            foreach ($info['skus'] as $skuSn => $skuId) {
                $key = self::key((string) $skuSn);
                if ($key === '') {
                    continue;
                }
                $rank = self::RANK[$info['status']] ?? 5; // unknown labels count as live-ish
                if (isset($skus[$key])) {
                    $cur = $skus[$key];
                    // Better status wins; same status -> the newer (higher) goods ID.
                    if ($cur['rank'] > $rank || ($cur['rank'] === $rank && (float) $cur['goods_id'] >= (float) $gid)) {
                        continue;
                    }
                }
                $skus[$key] = ['goods_id' => (string) $gid, 'sku_id' => (string) $skuId, 'status' => $info['status'], 'raw' => $info['raw'], 'rank' => $rank];
            }
        }

        $counts = [];
        foreach ($goods as $info) {
            $counts[$info['status']] = ($counts[$info['status']] ?? 0) + 1;
        }
        try {
            $this->syncLegacyCaches($goods);
        } catch (\Throwable $e) {
            Log::warning('TemuGoodsStatusSync: cache refresh failed', ['error' => $e->getMessage()]);
        }
        foreach ($goods as $gid => $info) {
            unset($goods[$gid]['skus']);
        }

        Storage::disk('local')->put(self::FILE, json_encode([
            'fetched_at' => now()->toIso8601String(),
            'counts' => $counts,
            'tabs' => $out['tabs'],
            'goods' => $goods,
            'skus' => $skus,
        ], JSON_UNESCAPED_UNICODE));
        self::$memo = false;

        $out['ok'] = true;
        $out['goods'] = count($goods);
        $out['skus'] = count($skus);
        $out['counts'] = $counts;

        return $out;
    }

    /** A goods in these states is not a published listing: its SKUs stay unlinked (Missing L). */
    public static function linkable(string $status): bool
    {
        return ! in_array($status, ['Deleted', 'Draft', 'Incomplete'], true);
    }

    /**
     * Replace the stored goods_id / sku_id of EVERY Temu 1 SKU with what Temu holds now (from the status read
     * above). A SKU that was listed on its own and later merged into another goods gets the new goods ID, a SKU
     * whose goods is deleted / draft / incomplete / gone loses its goods_id, and the saved buyer / seller links
     * that still carry the old ID are removed so they cannot bring the SKU back as Listed.
     *
     * Safety: refuses when Temu returned fewer than 20 live goods or when it would remove more than 80% of the
     * stored goods IDs (unless $force). Every changed row is written to storage/app/temu_cache/.
     *
     * @return array{ok: bool, live_goods: int, changed: int, relinked: int, added: int, cleared: int, links_cleared: int, note: string, sample: list<array<string, string>>}
     */
    public function rebuildLinks(bool $dryRun = false, bool $force = false): array
    {
        $r = ['ok' => false, 'live_goods' => 0, 'changed' => 0, 'relinked' => 0, 'added' => 0, 'cleared' => 0, 'links_cleared' => 0, 'note' => '', 'sample' => []];

        $data = self::read();
        if ($data === null) {
            $r['note'] = 'No Temu status data yet - run temu:goods-status first.';

            return $r;
        }

        $map = [];
        $liveGoods = [];
        foreach ((array) $data['skus'] as $key => $hit) {
            if (self::linkable((string) ($hit['status'] ?? ''))) {
                $map[$key] = $hit;
                $liveGoods[(string) $hit['goods_id']] = true;
            }
        }
        $r['live_goods'] = count($liveGoods);

        $rows = \App\Models\TemuMetric::query()
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->get(['id', 'sku', 'goods_id', 'sku_id']);
        $stored = $rows->filter(fn ($m) => trim((string) $m->goods_id) !== '')->count();

        $changes = [];
        foreach ($rows as $m) {
            $hit = $map[self::key((string) $m->sku)] ?? null;
            $oldGid = trim((string) $m->goods_id);
            $newGid = $hit ? (string) $hit['goods_id'] : '';
            $oldSku = trim((string) $m->sku_id);
            $newSku = ($hit && ($hit['sku_id'] ?? '') !== '') ? (string) $hit['sku_id'] : $oldSku;
            if ($oldGid === $newGid && $oldSku === $newSku) {
                continue;
            }
            $changes[] = [
                'id' => $m->id, 'sku' => (string) $m->sku,
                'old_goods_id' => $oldGid, 'new_goods_id' => $newGid,
                'old_sku_id' => $oldSku, 'new_sku_id' => $newSku,
            ];
            if ($oldGid !== '' && $newGid === '') {
                $r['cleared']++;
            } elseif ($oldGid !== '' && $oldGid !== $newGid) {
                $r['relinked']++;
            } elseif ($oldGid === '' && $newGid !== '') {
                $r['added']++;
            }
        }
        $r['changed'] = count($changes);
        $r['sample'] = array_map(fn ($c) => [
            'sku' => $c['sku'], 'old' => $c['old_goods_id'], 'new' => $c['new_goods_id'],
        ], array_slice($changes, 0, 40));

        if (! $force && ($r['live_goods'] < 20 || $r['cleared'] > max(25, (int) floor($stored * 0.8)))) {
            $r['note'] = "Temu returned {$r['live_goods']} live goods and would remove {$r['cleared']} of {$stored} stored goods IDs - nothing changed (use --force to override).";

            return $r;
        }

        $oldBySku = [];
        foreach ($changes as $c) {
            if ($c['old_goods_id'] !== '' && $c['old_goods_id'] !== $c['new_goods_id']) {
                $oldBySku[self::key($c['sku'])] = $c['old_goods_id'];
            }
        }

        $r['ok'] = true;
        if ($dryRun) {
            $r['links_cleared'] = $this->clearLinks($oldBySku, true);

            return $r;
        }

        if ($changes !== []) {
            try {
                Storage::disk('local')->put('temu_cache/temu_links_rebuild_'.now()->format('Ymd_His').'.json', json_encode($changes, JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $e) {
                $r['ok'] = false;
                $r['note'] = 'Could not write the restore file - nothing changed: '.$e->getMessage();

                return $r;
            }
            foreach ($changes as $c) {
                \App\Models\TemuMetric::query()->whereKey($c['id'])->update([
                    'goods_id' => $c['new_goods_id'] !== '' ? $c['new_goods_id'] : null,
                    'sku_id' => $c['new_sku_id'] !== '' ? $c['new_sku_id'] : null,
                ]);
            }
        }
        $r['links_cleared'] = $this->clearLinks($oldBySku, false);
        Log::info('TemuGoodsStatusSync: links rebuilt from Temu', array_diff_key($r, ['sample' => 1]));

        return $r;
    }

    /**
     * Remove the saved buyer / seller link of a SKU when it still carries that SKU's OLD goods ID.
     *
     * @param  array<string, string>  $oldBySkuKey  self::key(sku) => old goods_id
     */
    private function clearLinks(array $oldBySkuKey, bool $dryRun): int
    {
        if ($oldBySkuKey === []) {
            return 0;
        }
        $cfg = \App\Support\Marketplace\ChannelListingRegistry::get('temu') ?? [];
        $count = 0;
        foreach (['status', 'dataView'] as $which) {
            $class = $cfg[$which] ?? null;
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }
            $table = (new $class)->getTable();
            if (! \Illuminate\Support\Facades\Schema::hasTable($table) || ! \Illuminate\Support\Facades\Schema::hasColumn($table, 'value')) {
                continue;
            }
            $class::query()->whereNotNull('value')->orderBy('id')->chunkById(500, function ($rows) use ($oldBySkuKey, $dryRun, &$count) {
                foreach ($rows as $row) {
                    $old = $oldBySkuKey[self::key((string) $row->sku)] ?? null;
                    if ($old === null) {
                        continue;
                    }
                    $raw = $row->value;
                    $value = is_array($raw) ? $raw : json_decode((string) $raw, true);
                    if (! is_array($value)) {
                        continue;
                    }
                    $changed = false;
                    foreach (['buyer_link', 'seller_link'] as $field) {
                        if (! empty($value[$field]) && str_contains((string) $value[$field], $old)) {
                            unset($value[$field]);
                            $changed = true;
                        }
                    }
                    if ($changed) {
                        $count++;
                        if (! $dryRun) {
                            $row->value = is_array($raw) ? $value : json_encode($value);
                            $row->save();
                        }
                    }
                }
            });
        }

        return $count;
    }

    /**
     * The older caches the Temu Ads page and SKU VS Parent read (live goods list, SKU names per goods) are
     * replaced by / pruned to what Temu holds now, so deleted goods cannot linger in them.
     *
     * @param  array<string, array<string, mixed>>  $goods
     */
    private function syncLegacyCaches(array $goods): void
    {
        $live = [];
        foreach ($goods as $gid => $info) {
            if (($info['status'] ?? '') !== 'Deleted') {
                $live[(string) $gid] = $info['status'];
            }
        }
        if (count($live) < 20) {
            return;
        }
        \App\Console\Commands\SyncTemuGoodsSkuNames::putLiveGoods($live);

        $names = \App\Console\Commands\SyncTemuGoodsSkuNames::getSkuNames();
        $names = is_array($names) ? $names : [];
        foreach (array_keys($names) as $gid) {
            if (! isset($live[(string) $gid])) {
                unset($names[$gid]);
            }
        }
        foreach ($live as $gid => $status) {
            if (isset($names[$gid]) || empty($goods[$gid]['skus'])) {
                continue;
            }
            $skus = [];
            foreach ($goods[$gid]['skus'] as $skuSn => $skuId) {
                if ((string) $skuId !== '') {
                    $skus[(string) $skuId] = (string) $skuSn;
                }
            }
            if ($skus !== []) {
                $names[$gid] = ['out_goods_sn' => '', 'skus' => $skus];
            }
        }
        \App\Console\Commands\SyncTemuGoodsSkuNames::putSkuNames($names);
    }

    /** @return array<string, mixed>|null */
    public static function read(): ?array
    {
        if (self::$memo !== false) {
            return self::$memo;
        }
        try {
            $disk = Storage::disk('local');
            $data = $disk->exists(self::FILE) ? json_decode((string) $disk->get(self::FILE), true) : null;
        } catch (\Throwable $e) {
            $data = null;
        }

        return self::$memo = (is_array($data) && isset($data['skus'])) ? $data : null;
    }

    /**
     * Adds temu_status / temu_status_raw / temu_goods_id / temu_sku_id / temu_status_checked to listing rows.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    public static function attach(Collection $rows): Collection
    {
        $data = self::read();

        // Per-SKU active / inactive as Temu reported it on the last SKU sync (temu_metrics.listing_status).
        // Seller Center shows "1 of them inactive" per SKU, which the goods-level status cannot express, and it
        // is also what the column falls back to before the first full status read.
        $local = [];
        try {
            \App\Models\TemuMetric::query()
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->whereNotNull('listing_status')->where('listing_status', '!=', '')
                ->get(['sku', 'listing_status'])
                ->each(function ($m) use (&$local) {
                    $local[self::key((string) $m->sku)] = strtolower(trim((string) $m->listing_status));
                });
        } catch (\Throwable $e) {
            // column may not exist on old installs
        }

        $seen = self::readIncompleteSeen();

        foreach ($rows as $row) {
            $key = self::key((string) ($row->sku ?? ''));
            $row->temu_status_checked = $data !== null || $local !== [];
            $row->temu_status = null;
            $row->temu_status_raw = null;
            $row->temu_status_source = null;
            $row->temu_goods_id = null;
            $row->temu_sku_id = null;

            $hit = $data['skus'][$key] ?? null;
            if (is_array($hit)) {
                $row->temu_status = $hit['status'] ?? null;
                $row->temu_status_source = 'Seller Center';
                $row->temu_status_raw = ! empty($hit['raw']) ? json_encode($hit['raw'], JSON_UNESCAPED_UNICODE) : null;
                $row->temu_goods_id = $hit['goods_id'] ?? null;
                $row->temu_sku_id = ($hit['sku_id'] ?? '') !== '' ? $hit['sku_id'] : null;
            }

            if (! $row->temu_status && $key !== '' && trim((string) ($row->goods_id ?? '')) === '' && isset($seen[$key])) {
                // The goods list did not show it, but Temu told us when we tried to publish: it is Incomplete.
                $row->temu_status = 'Incomplete';
                $row->temu_status_source = 'Publish check';
                $row->temu_goods_id = $seen[$key]['goods_id'] ?? null;
                $row->temu_status_checked = true;
            }

            $localStatus = $local[$key] ?? '';
            if ($localStatus === 'inactive' && $row->temu_status === 'Active') {
                // active goods, but this SKU is switched off ("1 of them inactive" in Seller Center)
                $row->temu_status = 'Inactive';
                $row->temu_status_source = 'SKU status';
            } elseif ($data === null && ! $row->temu_status && trim((string) ($row->goods_id ?? '')) !== ''
                && in_array($localStatus, ['active', 'inactive'], true)) {
                // no full status read yet: show the last SKU sync until "Refresh listings" is run
                $row->temu_status = ucfirst($localStatus);
                $row->temu_status_source = 'last SKU sync';
            }
        }

        return $rows;
    }

    public const SEEN_FILE = 'temu_cache/temu1_incomplete_seen.json';

    /** @return array<string, array{goods_id: string, sku: string, seen_at: string}> */
    public static function readIncompleteSeen(): array
    {
        try {
            if (! Storage::disk('local')->exists(self::SEEN_FILE)) {
                return [];
            }
            $json = json_decode((string) Storage::disk('local')->get(self::SEEN_FILE), true);

            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Remember that Temu answered "Incomplete" for this SKU (used when the goods list does not show it). */
    public static function rememberIncomplete(string $sku, string $goodsId): void
    {
        $key = self::key($sku);
        if ($key === '') {
            return;
        }
        $all = self::readIncompleteSeen();
        $all[$key] = ['goods_id' => $goodsId, 'sku' => $sku, 'seen_at' => now()->toIso8601String()];
        try {
            Storage::disk('local')->put(self::SEEN_FILE, json_encode($all, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::warning('TemuGoodsStatusSync: could not remember incomplete SKU', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }

    public static function forgetIncomplete(string $sku): void
    {
        $key = self::key($sku);
        $all = self::readIncompleteSeen();
        if ($key === '' || ! isset($all[$key])) {
            return;
        }
        unset($all[$key]);
        try {
            Storage::disk('local')->put(self::SEEN_FILE, json_encode($all, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public static function key(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return '';
        }
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);

        return strtolower($norm !== '' ? $norm : $sku);
    }

    /**
     * Every page of one goods.list.retrieve search type; null when Temu refuses the call / the list is incomplete.
     *
     * @return list<array<string, mixed>>|null
     */
    private function pass(string $searchType): ?array
    {
        $found = [];
        $pageToken = null;
        $pages = 0;
        do {
            $body = ['goodsSearchType' => $searchType, 'pageSize' => 100];
            if ($pageToken) {
                $body['pageToken'] = $pageToken;
            }
            $res = $this->api->callOpenApi('temu.local.goods.list.retrieve', $body, 45);
            if (! ($res['success'] ?? false)) {
                Log::info('TemuGoodsStatusSync: search type not answered', ['type' => $searchType, 'error' => $res['errorMsg'] ?? $res['message'] ?? '']);

                return null;
            }
            foreach ((array) ($res['result']['goodsList'] ?? []) as $g) {
                if (is_array($g) && isset($g['goodsId'])) {
                    $found[] = $g;
                }
            }
            $pageToken = $res['result']['pagination']['nextToken'] ?? null;
            $pages++;
            usleep(150000);
        } while ($pageToken && $pages < 200);

        return $pageToken ? null : $found;
    }

    /** @param array<string, mixed> $g
     * @return array<string, string> skuSn => skuId */
    private function skusOf(array $g): array
    {
        $out = [];
        foreach ((array) ($g['skuInfoList'] ?? []) as $sku) {
            $sn = trim((string) ($sku['skuSn'] ?? $sku['outSkuSn'] ?? ''));
            if ($sn !== '') {
                $out[$sn] = (string) ($sku['skuId'] ?? '');
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $g
     * @return array<string, mixed> */
    private function rawStatusFields(array $g): array
    {
        return array_filter(
            array_intersect_key($g, array_flip(['goodsStatus', 'status', 'statusName', 'subStatus', 'goodsSubStatus', 'statusDesc', 'onSale'])),
            fn ($v) => $v !== null && $v !== ''
        );
    }

    /** @param array<string, mixed> $g */
    private function labelFromRecord(array $g): string
    {
        foreach (['statusName', 'goodsStatus', 'status', 'statusDesc', 'subStatus', 'goodsSubStatus'] as $field) {
            $value = $g[$field] ?? null;
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $label = self::labelFromValue($value);
            if ($label !== '') {
                return $label;
            }
        }
        $raw = $this->rawStatusFields($g);

        return $raw === [] ? 'Unknown' : 'Status '.reset($raw);
    }

    public static function labelFromValue(mixed $value): string
    {
        if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
            $code = (int) $value;
            if (in_array($code, [6, 4401], true)) {
                return 'Deleted';
            }
            if ($code === 5) {
                return 'Draft';
            }
            if ($code >= 3300 && $code < 3400) {
                return 'Incomplete';
            }
            if ($code >= 2200 && $code < 2300) {
                return 'Inactive';
            }
            if (in_array($code, [1, 1101, 1102], true)) {
                return 'Active';
            }

            return '';
        }
        $text = strtolower(trim((string) $value));
        if (preg_match('/delet|recycl|remov/', $text)) {
            return 'Deleted';
        }
        if (str_contains($text, 'draft')) {
            return 'Draft';
        }
        if (str_contains($text, 'incomplete')) {
            return 'Incomplete';
        }
        if (preg_match('/inactive|off sale|not on sale|offline/', $text)) {
            return 'Inactive';
        }
        if (preg_match('/active|on sale|online/', $text)) {
            return 'Active';
        }

        return '';
    }
}
