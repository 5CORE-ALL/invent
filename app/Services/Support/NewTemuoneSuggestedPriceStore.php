<?php

namespace App\Services\Support;

use App\Models\TemuDataView;
use App\Services\TemuShopifySalesService;
use App\Support\AmazonDilGroiRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Persist New Temu One suggested S PRC (Dil + CVR Target NROI) on temu_data_view
 * without touching Temu 1/2/3 SPRICE keys. Recalculate only when Dil/CVR rules
 * or pricing inputs change (fingerprint). Saved SGROI is live SPFT ÷ LP.
 */
class NewTemuoneSuggestedPriceStore
{
    public const KEY_SGROI = 'NTO_SGROI';

    public const KEY_SPRICE = 'NTO_SPRICE';

    public const KEY_S_BASE = 'NTO_S_BASE';

    public const KEY_SPRICE_DIL = 'NTO_SPRICE_DIL';

    public const KEY_FINGERPRINT = 'NTO_FINGERPRINT';

    public const KEY_CAPPED = 'NTO_CAPPED';

    public const KEY_LABELS = 'NTO_LABELS';

    public const KEY_LMP_ALERT = 'NTO_LMP_ALERT';

    public const KEY_PUSHED_BASE = 'NTO_PUSHED_BASE';

    public const KEY_PUSHED_AT = 'NTO_PUSHED_AT';

    public const KEY_PUSH_STATUS = 'NTO_PUSH_STATUS';

    /** @var array<string, TemuDataView> */
    private array $views = [];

    /** @var array<string, array<string, mixed>> */
    private array $dirty = [];

    /**
     * @param  list<string>  $skus
     */
    public function loadForSkus(array $skus): void
    {
        $this->views = [];
        $this->dirty = [];
        $unique = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $unique[$this->skuKey($sku)] = $sku;
            }
        }
        if ($unique === []) {
            return;
        }
        foreach (array_chunk(array_values($unique), 500) as $chunk) {
            $rows = TemuDataView::query()->whereIn('sku', $chunk)->get();
            foreach ($rows as $row) {
                $this->indexView($row);
            }
        }
        $missing = [];
        foreach ($unique as $key => $sku) {
            if (! isset($this->views[$key])) {
                $missing[] = $sku;
            }
        }
        foreach (array_chunk($missing, 200) as $chunk) {
            $bind = implode(',', array_fill(0, count($chunk), '?'));
            $uppers = array_map(fn (string $sku) => $this->skuKey($sku), $chunk);
            $rows = TemuDataView::query()
                ->whereRaw('UPPER(TRIM(sku)) IN ('.$bind.')', $uppers)
                ->get();
            foreach ($rows as $row) {
                $this->indexView($row);
            }
        }
    }

    /**
     * @param  array{
     *     inv: float,
     *     lp: float,
     *     ship: float,
     *     dil: float,
     *     temu_l30?: float|int,
     *     sold?: float|int,
     *     cvr: float,
     *     cvr60: float,
     *     ebay: float,
     *     amz: float,
     *     lmp: float|int|string|null
     * }  $inputs
     * @param  list<array<string, mixed>>  $dilRules
     * @param  array<string, mixed>|null  $cvrAdj
     * @return array{
     *     sgroi: float|null,
     *     sprice: float,
     *     s_base: float,
     *     sprc_dil: float,
     *     labels: list<string>,
     *     lmp_alert: bool,
     *     capped: bool,
     *     use_saved: bool
     * }
     */
    public function resolve(string $sku, array $inputs, array $dilRules, ?array $cvrAdj): array
    {
        $fp = $this->fingerprint($inputs, $dilRules, $cvrAdj);
        $saved = $this->savedValue($sku);
        if ($this->isReusable($saved, $fp)) {
            return $this->withPushMeta($this->hydrateSaved($saved), $saved);
        }

        $computed = $this->compute($inputs, $dilRules, $cvrAdj);
        $this->queueWrite($sku, $computed, $fp);

        return $this->withPushMeta($computed, $saved);
    }

    /**
     * Stamp New Temu One push only. Does not write Temu 1/2/3 SPRICE.
     */
    public static function markPushed(string $sku, float $base, float $full = 0.0): void
    {
        $sku = trim($sku);
        if ($sku === '' || ! ($base > 0)) {
            return;
        }
        $view = TemuDataView::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->first()
            ?? new TemuDataView(['sku' => $sku]);
        $value = is_array($view->value) ? $view->value : [];
        $value[self::KEY_PUSHED_BASE] = round($base, 2);
        $value[self::KEY_PUSHED_AT] = now()->toDateTimeString();
        $value[self::KEY_PUSH_STATUS] = 'pushed';
        $view->sku = $view->sku ?: $sku;
        $view->value = $value;
        $view->save();
    }

    public function pendingWriteCount(): int
    {
        return count($this->dirty);
    }

    public function flush(): void
    {
        if ($this->dirty === []) {
            return;
        }
        try {
            foreach (array_chunk($this->dirty, 50, true) as $chunk) {
                DB::transaction(function () use ($chunk) {
                    foreach ($chunk as $key => $patch) {
                        $view = $this->views[$key] ?? null;
                        $sku = (string) ($patch['_sku'] ?? $key);
                        unset($patch['_sku']);
                        if (! $view) {
                            $view = new TemuDataView(['sku' => $sku]);
                            $this->views[$key] = $view;
                        }
                        $value = is_array($view->value) ? $view->value : [];
                        foreach ($patch as $field => $val) {
                            if ($val === null) {
                                unset($value[$field]);
                            } else {
                                $value[$field] = $val;
                            }
                        }
                        $view->sku = $view->sku ?: $sku;
                        $view->value = $value;
                        $view->save();
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::error('New Temu One suggested price persist failed: '.$e->getMessage());
        }
        $this->dirty = [];
    }

    /**
     * Wipe stored New Temu One S PRC so the next resolve() back-solves SNROI again.
     */
    public function clearSprice(string $sku): void
    {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }
        $this->queueWrite($sku, [
            'sgroi' => null,
            'sprice' => 0.0,
            's_base' => 0.0,
            'sprc_dil' => 0.0,
            'labels' => [],
            'lmp_alert' => false,
            'capped' => false,
            'use_saved' => false,
        ], '');
    }

    /**
     * Persist a Dil SNROI S PRC (Amazon-style insert after clear). Empty fingerprint
     * so the next catalog load recomputes if Dil / CVR / ads inputs changed.
     * $sprcDil is the uncapped Dil $; $labels are Amz/EB caps on the painted cell.
     *
     * @param  list<string>  $labels
     */
    public function writeExactSprice(
        string $sku,
        float $sprice,
        float $lp = 0.0,
        float $ship = 0.0,
        float $sprcDil = 0.0,
        array $labels = []
    ): void {
        $sku = trim($sku);
        $sprice = round($sprice, 2);
        if ($sku === '' || ! ($sprice > 0)) {
            $this->clearSprice($sku);

            return;
        }
        $inverted = ($lp > 0)
            ? TemuShopifySalesService::sgroiAtSprice($sprice, $lp, $ship, 0.0)
            : null;
        $sBase = round(TemuShopifySalesService::computeBaseFromFullTemuPrice($sprice), 2);
        $uncapped = $sprcDil > 0 ? round($sprcDil, 2) : $sprice;
        $cleanLabels = [];
        foreach ($labels as $label) {
            $label = trim((string) $label);
            if ($label !== '' && ! in_array($label, $cleanLabels, true)) {
                $cleanLabels[] = $label;
            }
        }
        $this->queueWrite($sku, [
            'sgroi' => $inverted !== null ? round($inverted, 2) : null,
            'sprice' => $sprice,
            's_base' => $sBase > 0 ? $sBase : 0.0,
            'sprc_dil' => $uncapped,
            'labels' => $cleanLabels,
            'lmp_alert' => false,
            'capped' => abs($uncapped - $sprice) > 0.01,
            'use_saved' => true,
        ], '');
    }

    /**
     * S PRC from Dil + CVR Target NROI (SNROI), including channel Ads%.
     */
    public static function priceFromExactSgroi(float $lp, float $ship, float $sgroi, float $adsPercent = 0.0): float
    {
        $raw = TemuShopifySalesService::spriceFromTargetSnroi($lp, $ship, $sgroi, $adsPercent, 0.0);
        if (! is_finite($raw) || $raw < 0.01) {
            return 0.0;
        }

        return round($raw, 2);
    }

    /**
     * Dil slab + CVR overlay. Dil 100 + CVR +10 → 110 exactly.
     * Temu L30 = 0 (0 Sold) uses the lowest Target NROI in the table, same as Temu 1.
     */
    public static function targetSgroi(
        float $inv,
        float $dil,
        float $cvr,
        float $cvrPrior,
        array $dilRules,
        ?array $cvrAdj,
        bool $zeroSold = false
    ): ?float {
        if (! ($inv > 0)) {
            return null;
        }
        if ($zeroSold) {
            $slabGroi = AmazonDilGroiRule::minTarget($dilRules);
            if ($slabGroi === null) {
                return null;
            }
        } else {
            $rule = AmazonDilGroiRule::matchOrNearest($dil, $dilRules);
            if ($rule === null) {
                return null;
            }
            $slabGroi = (float) $rule['groi'];
        }
        $groi = AmazonDilGroiRule::adjustGroiForCvrArrow($slabGroi, $cvr, $cvrPrior, $cvrAdj);

        return is_finite($groi) ? (float) $groi : null;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  list<array<string, mixed>>  $dilRules
     * @param  array<string, mixed>|null  $cvrAdj
     * @return array{
     *     sgroi: float|null,
     *     sprice: float,
     *     s_base: float,
     *     sprc_dil: float,
     *     labels: list<string>,
     *     lmp_alert: bool,
     *     capped: bool,
     *     use_saved: bool
     * }
     */
    private function compute(array $inputs, array $dilRules, ?array $cvrAdj): array
    {
        $empty = [
            'sgroi' => null,
            'sprice' => 0.0,
            's_base' => 0.0,
            'sprc_dil' => 0.0,
            'labels' => [],
            'lmp_alert' => false,
            'capped' => false,
            'use_saved' => false,
        ];
        $inv = (float) ($inputs['inv'] ?? 0);
        $lp = (float) ($inputs['lp'] ?? 0);
        $ship = (float) ($inputs['ship'] ?? 0);
        $dil = (float) ($inputs['dil'] ?? 0);
        $cvr = (float) ($inputs['cvr'] ?? 0);
        $cvr60 = (float) ($inputs['cvr60'] ?? 0);
        $sold = (float) ($inputs['temu_l30'] ?? $inputs['sold'] ?? 0);
        if (! ($inv > 0) || ! ($lp > 0)) {
            return $empty;
        }
        $ruleSgroi = self::targetSgroi($inv, $dil, $cvr, $cvr60, $dilRules, $cvrAdj, $sold <= 0);
        if ($ruleSgroi === null) {
            return $empty;
        }
        $ads = (float) ($inputs['ads'] ?? 0);
        $sprcDil = self::priceFromExactSgroi($lp, $ship, $ruleSgroi, $ads);
        $cap = $this->capSprice(
            $sprcDil,
            (float) ($inputs['ebay'] ?? 0),
            (float) ($inputs['amz'] ?? 0),
            $inputs['lmp'] ?? 0
        );
        $sprice = (float) $cap['sprice'];
        if (! ($sprice > 0)) {
            return $empty;
        }
        $capped = $sprcDil > 0 && round($sprice, 2) !== round($sprcDil, 2);
        $inverted = TemuShopifySalesService::sgroiAtSprice($sprice, $lp, $ship, 0.0);
        $sgroi = $inverted !== null ? round($inverted, 2) : $ruleSgroi;
        $sBase = round(TemuShopifySalesService::computeBaseFromFullTemuPrice($sprice), 2);

        return [
            'sgroi' => $sgroi,
            'sprice' => round($sprice, 2),
            's_base' => $sBase > 0 ? $sBase : 0.0,
            'sprc_dil' => $sprcDil > 0 ? $sprcDil : 0.0,
            'labels' => $cap['labels'],
            'lmp_alert' => (bool) $cap['lmpAlert'],
            'capped' => $capped,
            'use_saved' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $saved
     * @return array{
     *     sgroi: float|null,
     *     sprice: float,
     *     s_base: float,
     *     sprc_dil: float,
     *     labels: list<string>,
     *     lmp_alert: bool,
     *     capped: bool,
     *     use_saved: bool
     * }
     */
    private function hydrateSaved(array $saved): array
    {
        $sprice = is_numeric($saved[self::KEY_SPRICE] ?? null) ? round((float) $saved[self::KEY_SPRICE], 2) : 0.0;
        $sgroi = is_numeric($saved[self::KEY_SGROI] ?? null) ? (float) $saved[self::KEY_SGROI] : null;
        $sBase = is_numeric($saved[self::KEY_S_BASE] ?? null) ? round((float) $saved[self::KEY_S_BASE], 2) : 0.0;
        if ($sprice > 0 && ! ($sBase > 0)) {
            $sBase = round(TemuShopifySalesService::computeBaseFromFullTemuPrice($sprice), 2);
        }
        $sprcDil = is_numeric($saved[self::KEY_SPRICE_DIL] ?? null)
            ? round((float) $saved[self::KEY_SPRICE_DIL], 2)
            : $sprice;
        $labels = $saved[self::KEY_LABELS] ?? [];
        if (! is_array($labels)) {
            $labels = [];
        }

        return [
            'sgroi' => $sgroi,
            'sprice' => $sprice,
            's_base' => $sBase > 0 ? $sBase : 0.0,
            'sprc_dil' => $sprcDil > 0 ? $sprcDil : 0.0,
            'labels' => array_values(array_map('strval', $labels)),
            'lmp_alert' => (bool) ($saved[self::KEY_LMP_ALERT] ?? false),
            'capped' => (bool) ($saved[self::KEY_CAPPED] ?? false),
            'use_saved' => $sprice > 0 && $sgroi !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $saved
     * @return array<string, mixed>
     */
    private function withPushMeta(array $row, array $saved): array
    {
        $sBase = (float) ($row['s_base'] ?? 0);
        $sprice = (float) ($row['sprice'] ?? 0);
        $pushedBase = is_numeric($saved[self::KEY_PUSHED_BASE] ?? null)
            ? round((float) $saved[self::KEY_PUSHED_BASE], 2)
            : 0.0;
        if (! ($pushedBase > 0) && is_numeric($saved['SPRICE_PUSHED_VALUE'] ?? null)) {
            $full = round((float) $saved['SPRICE_PUSHED_VALUE'], 2);
            if ($full > 0) {
                $pushedBase = round(TemuShopifySalesService::computeBaseFromFullTemuPrice($full), 2);
            }
        }
        $matched = $pushedBase > 0 && $sBase > 0 && abs($pushedBase - $sBase) < 0.015;
        if (! $matched && $sprice > 0 && is_numeric($saved['SPRICE_PUSHED_VALUE'] ?? null)) {
            $matched = abs(round((float) $saved['SPRICE_PUSHED_VALUE'], 2) - round($sprice, 2)) < 0.015;
        }
        $row['pushed_base'] = $pushedBase > 0 ? $pushedBase : null;
        $row['pushed_at'] = $saved[self::KEY_PUSHED_AT] ?? $saved['SPRICE_PUSHED_AT'] ?? null;
        $row['push_status'] = $matched ? 'pushed' : null;

        return $row;
    }

    /**
     * @param  array<string, mixed>  $saved
     */
    private function isReusable(array $saved, string $fp): bool
    {
        if ($fp === '' || ($saved[self::KEY_FINGERPRINT] ?? null) !== $fp) {
            return false;
        }
        if (! isset($saved[self::KEY_SGROI], $saved[self::KEY_SPRICE])) {
            return false;
        }
        if (! is_numeric($saved[self::KEY_SGROI]) || ! is_numeric($saved[self::KEY_SPRICE])) {
            return false;
        }

        return true;
    }

    /**
     * @param  array{
     *     sgroi: float|null,
     *     sprice: float,
     *     s_base: float,
     *     sprc_dil: float,
     *     labels: list<string>,
     *     lmp_alert: bool,
     *     capped: bool,
     *     use_saved: bool
     * }  $computed
     */
    private function queueWrite(string $sku, array $computed, string $fp): void
    {
        $key = $this->skuKey($sku);
        $saved = $this->savedValue($sku);
        $patch = [
            '_sku' => $sku,
            self::KEY_SGROI => $computed['sgroi'],
            self::KEY_SPRICE => $computed['sprice'] > 0 ? $computed['sprice'] : null,
            self::KEY_S_BASE => $computed['s_base'] > 0 ? $computed['s_base'] : null,
            self::KEY_SPRICE_DIL => $computed['sprc_dil'] > 0 ? $computed['sprc_dil'] : null,
            self::KEY_FINGERPRINT => $fp,
            self::KEY_CAPPED => $computed['capped'] ? true : null,
            self::KEY_LABELS => $computed['labels'] !== [] ? $computed['labels'] : null,
            self::KEY_LMP_ALERT => $computed['lmp_alert'] ? true : null,
        ];
        if ($this->samePersisted($saved, $patch, $fp)) {
            return;
        }
        $this->dirty[$key] = $patch;
    }

    /**
     * @param  array<string, mixed>  $saved
     * @param  array<string, mixed>  $patch
     */
    private function samePersisted(array $saved, array $patch, string $fp): bool
    {
        if (($saved[self::KEY_FINGERPRINT] ?? null) !== $fp) {
            return false;
        }
        foreach ([self::KEY_SGROI, self::KEY_SPRICE, self::KEY_S_BASE, self::KEY_SPRICE_DIL] as $field) {
            $a = $saved[$field] ?? null;
            $b = $patch[$field] ?? null;
            if ($a === null && $b === null) {
                continue;
            }
            if (! is_numeric($a) || ! is_numeric($b) || abs((float) $a - (float) $b) > 0.001) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  list<array<string, mixed>>  $dilRules
     * @param  array<string, mixed>|null  $cvrAdj
     */
    private function fingerprint(array $inputs, array $dilRules, ?array $cvrAdj): string
    {
        $normRules = [];
        foreach (AmazonDilGroiRule::normalizeList($dilRules) as $rule) {
            $normRules[] = [
                'min' => round((float) $rule['min'], 2),
                'max' => round((float) $rule['max'], 2),
                'groi' => round((float) $rule['groi'], 2),
            ];
        }
        $cvr = AmazonDilGroiRule::normalizeCvrAdj($cvrAdj);
        $lmp = $inputs['lmp'] ?? 0;
        $payload = [
            'v' => 6,
            'ads' => round((float) ($inputs['ads'] ?? 0), 2),
            'rules' => $normRules,
            'temu_l30' => (int) ($inputs['temu_l30'] ?? $inputs['sold'] ?? 0),
            'cvr_adj' => [
                'down_lt' => round((float) $cvr['down_lt'], 2),
                'down_adj' => round((float) $cvr['down_adj'], 2),
                'up_gt' => round((float) $cvr['up_gt'], 2),
                'up_adj' => round((float) $cvr['up_adj'], 2),
            ],
            'dil' => round((float) ($inputs['dil'] ?? 0), 2),
            'cvr' => round((float) ($inputs['cvr'] ?? 0), 2),
            'cvr60' => round((float) ($inputs['cvr60'] ?? 0), 2),
            'lp' => round((float) ($inputs['lp'] ?? 0), 2),
            'ship' => round((float) ($inputs['ship'] ?? 0), 2),
            'inv' => round((float) ($inputs['inv'] ?? 0), 2),
            'ebay' => round((float) ($inputs['ebay'] ?? 0), 2),
            'amz' => round((float) ($inputs['amz'] ?? 0), 2),
            'lmp' => (is_numeric($lmp) && (float) $lmp > 0) ? round((float) $lmp, 2) : 0.0,
        ];

        return hash('sha1', (string) json_encode($payload, JSON_PRESERVE_ZERO_FRACTION));
    }

    /**
     * @return array{sprice: float, labels: list<string>, lmpAlert: bool}
     */
    private function capSprice(float $discounted, float $ebay, float $amz, $lmp): array
    {
        $sprice = $discounted > 0 ? $discounted : 0.0;
        if (! ($sprice > 0)) {
            return ['sprice' => 0.0, 'labels' => [], 'lmpAlert' => false];
        }
        $ebay = $ebay > 0 ? round($ebay, 2) : 0.0;
        $amz = $amz > 0 ? round($amz, 2) : 0.0;
        $lmp = (is_numeric($lmp) && (float) $lmp > 0) ? round((float) $lmp, 2) : 0.0;
        if ($ebay > 0 && $sprice > $ebay) {
            $sprice = round($ebay, 2);
        }
        if ($amz > 0 && $sprice > $amz) {
            $sprice = round($amz, 2);
        }
        if ($lmp > 0 && $sprice > $lmp) {
            $sprice = round($lmp, 2);
        }
        $labels = [];
        if ($ebay > 0 && abs(round($sprice, 2) - $ebay) < 0.015 && $discounted > ($ebay + 0.004)) {
            $labels[] = 'EB';
        }
        if ($amz > 0 && abs(round($sprice, 2) - $amz) < 0.015 && $discounted > ($amz + 0.004)) {
            $labels[] = 'Amz';
        }

        return [
            'sprice' => round($sprice, 2),
            'labels' => $labels,
            'lmpAlert' => $lmp > 0 && round($sprice, 2) === $lmp && $discounted > $lmp,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function savedValue(string $sku): array
    {
        $view = $this->views[$this->skuKey($sku)] ?? null;
        if (! $view) {
            return [];
        }
        $value = $view->value;

        return is_array($value) ? $value : [];
    }

    private function indexView(TemuDataView $row): void
    {
        $key = $this->skuKey((string) $row->sku);
        if ($key === '' || isset($this->views[$key])) {
            return;
        }
        $this->views[$key] = $row;
    }

    private function skuKey(string $sku): string
    {
        return strtoupper(trim($sku));
    }
}
