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
                $rank = self::RANK[$info['status']] ?? 2;
                if (isset($skus[$key]) && $skus[$key]['rank'] >= $rank) {
                    continue;
                }
                $skus[$key] = ['goods_id' => (string) $gid, 'sku_id' => (string) $skuId, 'status' => $info['status'], 'raw' => $info['raw'], 'rank' => $rank];
            }
        }

        $counts = [];
        foreach ($goods as $info) {
            $counts[$info['status']] = ($counts[$info['status']] ?? 0) + 1;
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
        foreach ($rows as $row) {
            $row->temu_status_checked = $data !== null;
            $row->temu_status = null;
            $row->temu_status_raw = null;
            $row->temu_goods_id = null;
            $row->temu_sku_id = null;
            if ($data === null) {
                continue;
            }
            $hit = $data['skus'][self::key((string) ($row->sku ?? ''))] ?? null;
            if (! is_array($hit)) {
                continue;
            }
            $row->temu_status = $hit['status'] ?? null;
            $row->temu_status_raw = ! empty($hit['raw']) ? json_encode($hit['raw'], JSON_UNESCAPED_UNICODE) : null;
            $row->temu_goods_id = $hit['goods_id'] ?? null;
            $row->temu_sku_id = ($hit['sku_id'] ?? '') !== '' ? $hit['sku_id'] : null;
        }

        return $rows;
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
