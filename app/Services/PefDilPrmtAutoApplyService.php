<?php

namespace App\Services;

use App\Models\ChannelTabulatorColumnSetting;
use Illuminate\Support\Facades\Log;

/**
 * Midnight Dil vs PRMT auto-apply for PEF eBay1.
 * Always runs once per day (even if Dil/INV/rules unchanged):
 * INV = 0 → PRMT% = 0 (pause); else map Dil% slab → PRMT% and sync Promotion API.
 */
class PefDilPrmtAutoApplyService
{
    /** @var list<array{key:string,label:string,prmt:float|int}> */
    public const DEFAULT_RULES = [
        ['key' => '0-10', 'label' => '0–10%', 'prmt' => 10],
        ['key' => '10-20', 'label' => '10–20%', 'prmt' => 9],
        ['key' => '20-30', 'label' => '20–30%', 'prmt' => 8],
        ['key' => '30-40', 'label' => '30–40%', 'prmt' => 7],
        ['key' => '40-50', 'label' => '40–50%', 'prmt' => 6],
        ['key' => '50-60', 'label' => '50–60%', 'prmt' => 5],
        ['key' => '60-70', 'label' => '60–70%', 'prmt' => 4],
        ['key' => '70-80', 'label' => '70–80%', 'prmt' => 3],
        ['key' => '80-90', 'label' => '80–90%', 'prmt' => 2],
        ['key' => '90-100', 'label' => '90–100%', 'prmt' => 1],
        ['key' => 'gt-100', 'label' => '> 100%', 'prmt' => 0],
    ];

    public function __construct(
        private readonly PricingErrorsFixCvrCacheBuilder $pefBuilder,
        private readonly Ebay1PromotionService $promotion
    ) {}

    /**
     * @return list<array{key:string,label:string,prmt:float}>
     */
    public function loadRules(): array
    {
        $defaults = self::DEFAULT_RULES;
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', 'pef_dil_vs_prmt')
            ->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        if (! is_array($saved) || $saved === []) {
            return array_map(static fn ($r) => [
                'key' => $r['key'],
                'label' => $r['label'],
                'prmt' => (float) $r['prmt'],
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
            $prmt = array_key_exists($k, $byKey) && is_numeric($byKey[$k]['prmt'] ?? null)
                ? (float) $byKey[$k]['prmt']
                : (float) $def['prmt'];
            if ($prmt < 0) {
                $prmt = 0.0;
            }
            $rules[] = [
                'key' => $k,
                'label' => $def['label'],
                'prmt' => $prmt,
            ];
        }

        return $rules;
    }

    public function dilSlabKey(float $dil): string
    {
        if (! is_finite($dil) || $dil < 0) {
            return '0-10';
        }
        if ($dil > 100) {
            return 'gt-100';
        }
        if ($dil >= 90) {
            return '90-100';
        }
        if ($dil >= 80) {
            return '80-90';
        }
        if ($dil >= 70) {
            return '70-80';
        }
        if ($dil >= 60) {
            return '60-70';
        }
        if ($dil >= 50) {
            return '50-60';
        }
        if ($dil >= 40) {
            return '40-50';
        }
        if ($dil >= 30) {
            return '30-40';
        }
        if ($dil >= 20) {
            return '20-30';
        }
        if ($dil >= 10) {
            return '10-20';
        }

        return '0-10';
    }

    /**
     * @param  list<array{key:string,label:string,prmt:float}>  $rules
     */
    public function prmtForDil(float $dil, array $rules): float
    {
        $key = $this->dilSlabKey($dil);
        foreach ($rules as $rule) {
            if (($rule['key'] ?? '') === $key) {
                $n = (float) ($rule['prmt'] ?? 0);

                return is_finite($n) && $n >= 0 ? $n : 0.0;
            }
        }

        return 0.0;
    }

    /**
     * Always apply Dil→PRMT for listed eBay1 PEF rows (INV=0 forces 0).
     *
     * @param  callable(string):void|null  $logger
     * @return array{candidates:int,ok:int,failed:int,paused:int,skipped:int,errors:list<string>}
     */
    public function run(bool $dryRun = false, ?int $limit = null, int $sleepMs = 250, ?callable $logger = null): array
    {
        $log = $logger ?? static function (string $msg): void {};
        $rules = $this->loadRules();
        $log('Loaded '.count($rules).' Dil vs PRMT rules');

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
            $dil = (float) ($row['dil'] ?? 0);
            // Match PEF UI: INV = 0 → PRMT% = 0 (pause promotion)
            $prmt = ! ($inv > 0) ? 0.0 : $this->prmtForDil($dil, $rules);

            if ($dryRun) {
                $log(sprintf(
                    '  DRY %s inv=%s dil=%s → prmt=%s',
                    $sku,
                    rtrim(rtrim(number_format($inv, 2, '.', ''), '0'), '.') ?: '0',
                    rtrim(rtrim(number_format($dil, 2, '.', ''), '0'), '.') ?: '0',
                    rtrim(rtrim(number_format($prmt, 2, '.', ''), '0'), '.') ?: '0'
                ));
                $stats['ok']++;
                if ($prmt <= 0) {
                    $stats['paused']++;
                }
                continue;
            }

            try {
                $result = $this->promotion->syncSkuPromotionPercent($sku, $prmt);
                if (! empty($result['success'])) {
                    $stats['ok']++;
                    if ($prmt <= 0 || ! empty($result['paused'])) {
                        $stats['paused']++;
                    }
                } else {
                    $stats['failed']++;
                    $msg = $sku.': '.((string) ($result['message'] ?? 'promotion sync failed'));
                    $stats['errors'][] = $msg;
                    Log::warning('pef:dil-prmt-auto-apply failed', ['sku' => $sku, 'prmt' => $prmt, 'result' => $result]);
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                $msg = $sku.': '.$e->getMessage();
                $stats['errors'][] = $msg;
                Log::error('pef:dil-prmt-auto-apply exception', ['sku' => $sku, 'error' => $e->getMessage()]);
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        return $stats;
    }
}
