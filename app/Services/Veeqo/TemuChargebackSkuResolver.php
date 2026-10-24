<?php

namespace App\Services\Veeqo;

use App\Services\Temu2ApiService;
use App\Services\Temu3ApiService;
use App\Services\TemuApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Temu bill rows are keyed by the shipping label order (DO…), which no order table has. For Temu orders
 * shipped around each label's date, read the order detail and shipment result from the Temu API and
 * store the order's seller SKUs under every DO id found there (veeqo_order_skus, read by OrderSkuLookup).
 */
class TemuChargebackSkuResolver
{
    public const CHANNELS = [
        'temu1' => ['service' => TemuApiService::class, 'source' => 'temu1_csv', 'name' => 'Temu 1',
            'orders' => 'temu_orders', 'parent' => 'parent_order_sn', 'sku' => 'ext_code', 'time' => 'order_shipping_time', 'before' => 2],
        'temu2' => ['service' => Temu2ApiService::class, 'source' => 'temu2_csv', 'name' => 'Temu 2',
            'orders' => 'temu2_orders', 'parent' => 'parent_order_sn', 'sku' => 'ext_code', 'time' => 'order_shipping_time', 'before' => 2],
        'temu3' => ['service' => Temu3ApiService::class, 'source' => 'temu3_csv', 'name' => 'Temu 3',
            'orders' => 'temu3_orders', 'parent' => 'order_id', 'sku' => 'contribution_sku', 'time' => 'purchase_date', 'before' => 6],
    ];

    private const LABEL_ID = '/DO\d{20,}/';

    private const DAYS_AFTER = 2;

    /**
     * @param  callable(string): void|null  $log
     * @return array{missing: int, orders_checked: int, labels_found: int, error: ?string}
     */
    public function resolve(string $channel, int $limit, bool $probe = false, ?callable $log = null): array
    {
        $cfg = self::CHANNELS[$channel];
        $log ??= fn () => null;
        $out = ['missing' => 0, 'orders_checked' => 0, 'labels_found' => 0, 'error' => null];

        $missing = DB::table('veeqo_chargebacks as c')
            ->leftJoin('veeqo_order_skus as s', 's.order_number', '=', 'c.order_number')
            ->where('c.source', $cfg['source'])->where('c.order_number', 'like', 'DO%')
            ->where(fn ($q) => $q->whereNull('s.id')->orWhere('s.found', false))
            ->distinct()->pluck('c.order_number')->all();
        $out['missing'] = count($missing);
        if ($missing === []) {
            return $out;
        }

        $days = [];
        foreach ($missing as $do) {
            if (preg_match('/^DO\d{2}(\d{6})/', $do, $m)) {
                $days[$m[1]] = true;
            }
        }
        $windows = collect(array_keys($days))->map(fn ($d) => Carbon::createFromFormat('ymd', $d)->startOfDay())->sortDesc()->values();

        $api = app($cfg['service']);
        foreach ($windows as $day) {
            $parents = DB::table($cfg['orders'])
                ->whereBetween($cfg['time'], [$day->copy()->subDays($cfg['before']), $day->copy()->addDays(self::DAYS_AFTER + 1)])
                ->whereNotNull($cfg['parent'])->where($cfg['parent'], '!=', '')
                ->groupBy($cfg['parent'])
                ->selectRaw($cfg['parent']." AS parent_order_sn, GROUP_CONCAT(DISTINCT NULLIF({$cfg['sku']}, '') SEPARATOR ', ') AS skus")
                ->get();

            foreach ($parents as $p) {
                if ($out['orders_checked'] >= $limit) {
                    return $out;
                }
                $cacheKey = 'temu_label_checked:'.$channel.':'.$p->parent_order_sn;
                if (! $probe && Cache::has($cacheKey)) {
                    continue;
                }

                $detail = $api->callOpenApi('bg.order.detail.v2.get', ['parentOrderSn' => $p->parent_order_sn]);
                if (empty($detail['success'])) {
                    $out['error'] = (string) ($detail['message'] ?? 'bg.order.detail.v2.get failed');
                    if (str_contains($out['error'], 'IP_WHITE_LIST')) {
                        return $out;
                    }

                    continue;
                }
                $out['orders_checked']++;
                $json = json_encode($detail['result'] ?? []);

                preg_match_all('/"(?:mainPackageSn|packageSn)"\s*:\s*"([^"]+)"/', $json, $pk);
                $packages = array_values(array_unique($pk[1]));
                $shipmentJson = '';
                if ($packages !== []) {
                    $shipment = $api->callOpenApi('bg.logistics.shipment.result.get', ['packageSnList' => $packages]);
                    $shipmentJson = json_encode($shipment['result'] ?? $shipment);
                }

                preg_match_all(self::LABEL_ID, $json.$shipmentJson, $m);
                $labels = array_values(array_unique($m[0]));
                if ($probe) {
                    $log('== '.$p->parent_order_sn.' ('.$p->skus.') packages: '.implode(', ', $packages).' | DO ids: '.($labels ? implode(', ', $labels) : 'none'));
                    $log('order detail: '.mb_substr($json, 0, 3000));
                    $log('shipment result: '.mb_substr($shipmentJson, 0, 3000));
                }

                foreach ($labels as $label) {
                    DB::table('veeqo_order_skus')->updateOrInsert(
                        ['order_number' => $label],
                        [
                            'channel_name' => $cfg['name'],
                            'skus' => $p->skus ?: null,
                            'found' => (bool) $p->skus,
                            'checked_at' => now(),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                    $out['labels_found']++;
                    if (! $probe) {
                        $log($label.' → '.$p->parent_order_sn.' ('.($p->skus ?: 'no SKU').')');
                    }
                }
                Cache::put($cacheKey, true, now()->addDays(30));
                usleep(200000);
            }
        }

        return $out;
    }
}
