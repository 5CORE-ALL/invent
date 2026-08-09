<?php

namespace App\Services;

use App\Models\ChannelTabulatorColumnSetting;
use Illuminate\Support\Facades\Log;

/**
 * Midnight CVR vs CPN auto-apply for PEF eBay1 (runs after Dil/PRMT / price window).
 * Always runs once per day (even if CVR/rules unchanged):
 * INV = 0 → CPN% = 0 (pause); else map CVR% slab → CPN% and sync Coupon API.
 */
class PefCvrCpnAutoApplyService
{
    /** @var list<array{key:string,label:string,cpn:float|int}> */
    public const DEFAULT_RULES = [
        ['key' => 'eq-0', 'label' => '0%', 'cpn' => 10],
        ['key' => '0.01-1', 'label' => '0.01–1%', 'cpn' => 9],
        ['key' => '1-1.5', 'label' => '1–1.5%', 'cpn' => 8],
        ['key' => '1.5-2', 'label' => '1.5–2%', 'cpn' => 7],
        ['key' => '2-3', 'label' => '2–3%', 'cpn' => 6],
        ['key' => '3-4', 'label' => '3–4%', 'cpn' => 5],
        ['key' => '4-5', 'label' => '4–5%', 'cpn' => 4],
        ['key' => '5-6', 'label' => '5–6%', 'cpn' => 3],
        ['key' => '6-6.5', 'label' => '6–6.5%', 'cpn' => 2],
        ['key' => '6.5-7', 'label' => '6.5–7%', 'cpn' => 1],
        ['key' => 'gt-7', 'label' => '> 7%', 'cpn' => 0],
    ];

    public function __construct(
        private readonly PricingErrorsFixCvrCacheBuilder $pefBuilder,
        private readonly Ebay1CouponService $coupon
    ) {}

    /**
     * @return list<array{key:string,label:string,cpn:float}>
     */
    public function loadRules(): array
    {
        $defaults = self::DEFAULT_RULES;
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'pef_cvr_vs_cpn')
            ->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        if (! is_array($saved) || $saved === []) {
            return array_map(static fn ($r) => [
                'key' => $r['key'],
                'label' => $r['label'],
                'cpn' => (float) $r['cpn'],
            ], $defaults);
        }

        $byKey = [];
        foreach ($saved as $item) {
            if (! is_array($item)) {
                continue;
            }
            $k = (string) ($item['key'] ?? '');
            if ($k !== '') {
                $byKey[$k] = $item;
            }
        }

        $rules = [];
        foreach ($defaults as $def) {
            $k = $def['key'];
            $cpn = array_key_exists($k, $byKey) && is_numeric($byKey[$k]['cpn'] ?? null)
                ? (float) $byKey[$k]['cpn']
                : (float) $def['cpn'];
            if ($cpn < 0) {
                $cpn = 0.0;
            }
            $rules[] = [
                'key' => $k,
                'label' => $def['label'],
                'cpn' => $cpn,
            ];
        }

        return $rules;
    }

    public function cvrSlabKey(float $cvr): string
    {
        if (! is_finite($cvr) || $cvr <= 0) {
            return 'eq-0';
        }
        if ($cvr > 7) {
            return 'gt-7';
        }
        if ($cvr >= 6.5) {
            return '6.5-7';
        }
        if ($cvr >= 6) {
            return '6-6.5';
        }
        if ($cvr >= 5) {
            return '5-6';
        }
        if ($cvr >= 4) {
            return '4-5';
        }
        if ($cvr >= 3) {
            return '3-4';
        }
        if ($cvr >= 2) {
            return '2-3';
        }
        if ($cvr >= 1.5) {
            return '1.5-2';
        }
        if ($cvr >= 1) {
            return '1-1.5';
        }

        return '0.01-1';
    }

    /**
     * @param  list<array{key:string,label:string,cpn:float}>  $rules
     */
    public function cpnForCvr(float $cvr, array $rules): float
    {
        $key = $this->cvrSlabKey($cvr);
        foreach ($rules as $rule) {
            if (($rule['key'] ?? '') === $key) {
                $n = (float) ($rule['cpn'] ?? 0);

                return is_finite($n) && $n >= 0 ? $n : 0.0;
            }
        }

        return 0.0;
    }

    /** Resolve CVR% the same way as PEF UI apply. */
    public function resolveCvr(array $row): float
    {
        $cvr = isset($row['cvr']) ? (float) $row['cvr'] : NAN;
        if (is_finite($cvr) && $cvr >= 0) {
            return round($cvr, 2);
        }
        $views = (float) ($row['views'] ?? 0);
        $l30 = (float) ($row['l30'] ?? 0);
        if ($views > 0) {
            return round(($l30 / $views) * 100, 2);
        }

        return 0.0;
    }

    /**
     * Always apply CVR→CPN for listed eBay1 PEF rows.
     *
     * @param  callable(string):void|null  $logger
     * @return array{candidates:int,ok:int,failed:int,paused:int,skipped:int,errors:list<string>}
     */
    public function run(bool $dryRun = false, ?int $limit = null, int $sleepMs = 250, ?callable $logger = null): array
    {
        $log = $logger ?? static function (string $msg): void {};
        $rules = $this->loadRules();
        $log('Loaded '.count($rules).' CVR vs CPN rules');

        $built = $this->pefBuilder->build(['ebay'], null, true);
        $rows = array_values(array_filter(
            $built['rows'] ?? [],
            static fn ($r) => is_array($r) && strtolower((string) ($r['marketplace'] ?? '')) === 'ebay1'
        ));

        if ($limit !== null) {
            $rows = array_slice($rows, 0, max(1, $limit));
        }

        $stats = [
            'candidates' => count($rows),
            'ok' => 0,
            'failed' => 0,
            'paused' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
        $log('eBay1 candidates: '.$stats['candidates']
            .($dryRun ? ' [DRY RUN — no API calls]' : ''));

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                $stats['skipped']++;
                continue;
            }

            $inv = (float) ($row['inv'] ?? 0);
            $cvr = $this->resolveCvr($row);
            // Match PEF UI: INV = 0 → CPN% = 0 (pause coupon)
            $cpn = ! ($inv > 0) ? 0.0 : $this->cpnForCvr($cvr, $rules);

            if ($dryRun) {
                $log(sprintf(
                    '  DRY %s inv=%s cvr=%s → cpn=%s',
                    $sku,
                    rtrim(rtrim(number_format($inv, 2, '.', ''), '0'), '.') ?: '0',
                    rtrim(rtrim(number_format($cvr, 2, '.', ''), '0'), '.') ?: '0',
                    rtrim(rtrim(number_format($cpn, 2, '.', ''), '0'), '.') ?: '0'
                ));
                $stats['ok']++;
                if ($cpn <= 0) {
                    $stats['paused']++;
                }
                continue;
            }

            try {
                $result = $this->coupon->syncSkuCouponPercent($sku, $cpn);
                if (! empty($result['success'])) {
                    $stats['ok']++;
                    if ($cpn <= 0 || ! empty($result['paused'])) {
                        $stats['paused']++;
                    }
                } else {
                    $stats['failed']++;
                    $msg = $sku.': '.((string) ($result['message'] ?? 'coupon sync failed'));
                    $stats['errors'][] = $msg;
                    Log::warning('pef:cvr-cpn-auto-apply failed', ['sku' => $sku, 'cpn' => $cpn, 'result' => $result]);
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                $msg = $sku.': '.$e->getMessage();
                $stats['errors'][] = $msg;
                Log::error('pef:cvr-cpn-auto-apply exception', ['sku' => $sku, 'error' => $e->getMessage()]);
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        return $stats;
    }
}
