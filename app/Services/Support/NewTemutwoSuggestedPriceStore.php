<?php

namespace App\Services\Support;

use App\Models\Temu2DataView;
use App\Services\TemuShopifySalesService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * New Temu Two S PRC + push stamps on temu2_data_view (NT2_* keys, never Temu 1/2/3 SPRICE).
 * Written only by the server job (newtemutwo:sprc-dil-auto-push) and the manual save endpoint.
 * The page and dataJson only read it. Saved SGROI is live SPFT ÷ LP at that S PRC.
 */
class NewTemutwoSuggestedPriceStore
{
    public const KEY_SGROI = 'NT2_SGROI';

    public const KEY_SPRICE = 'NT2_SPRICE';

    public const KEY_S_BASE = 'NT2_S_BASE';

    public const KEY_SPRICE_DIL = 'NT2_SPRICE_DIL';

    public const KEY_FINGERPRINT = 'NT2_FINGERPRINT';

    public const KEY_CAPPED = 'NT2_CAPPED';

    public const KEY_LABELS = 'NT2_LABELS';

    public const KEY_LMP_ALERT = 'NT2_LMP_ALERT';

    public const KEY_PUSHED_BASE = 'NT2_PUSHED_BASE';

    public const KEY_PUSHED_AT = 'NT2_PUSHED_AT';

    public const KEY_PUSH_STATUS = 'NT2_PUSH_STATUS';

    public const KEY_PUSH_ERROR_AT = 'NT2_PUSH_ERROR_AT';

    /** Promotion % last enrolled on Temu 2 (= Sum disc at that moment). */
    public const KEY_PM_PCT = 'NT2_PM_PCT';

    public const KEY_PM_AT = 'NT2_PM_AT';

    public const KEY_PM_ACTIVITY = 'NT2_PM_ACTIVITY';

    public const KEY_PM_DRAFT = 'NT2_PM_DRAFT';

    public const KEY_PM_PRICE = 'NT2_PM_PRICE';

    /** @var array<string, Temu2DataView> */
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
            $rows = Temu2DataView::query()->whereIn('sku', $chunk)->get();
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
            $rows = Temu2DataView::query()
                ->whereRaw('UPPER(TRIM(sku)) IN ('.$bind.')', $uppers)
                ->get();
            foreach ($rows as $row) {
                $this->indexView($row);
            }
        }
    }

    /**
     * Read-only saved S PRC (+ push stamp) for one SKU. Never recomputes and never writes.
     * The server job (newtemutwo:sprc-dil-auto-push) is the only writer of S PRC.
     * Call loadForSkus() first.
     *
     * @return array<string, mixed>
     */
    public function readSaved(string $sku): array
    {
        $saved = $this->savedValue($sku);

        return $this->withPushMeta($this->hydrateSaved($saved), $saved);
    }

    /**
     * Stamp New Temu Two push only. Does not write Temu 1/2/3 SPRICE.
     */
    public static function markPushed(string $sku, float $base, float $full = 0.0): void
    {
        $sku = trim($sku);
        if ($sku === '' || ! ($base > 0)) {
            return;
        }
        $view = Temu2DataView::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->first()
            ?? new Temu2DataView(['sku' => $sku]);
        $value = is_array($view->value) ? $view->value : [];
        $value[self::KEY_PUSHED_BASE] = round($base, 2);
        $value[self::KEY_PUSHED_AT] = now()->toDateTimeString();
        $value[self::KEY_PUSH_STATUS] = 'pushed';
        unset($value[self::KEY_PUSH_ERROR_AT]);
        $view->sku = $view->sku ?: $sku;
        $view->value = $value;
        $view->save();
    }

    /**
     * Stamp a successful Temu 2 promotion enroll. Does not touch S PRC / S Base keys.
     */
    public static function markPromotionApplied(string $sku, float $pct, float $price, string $activityId, ?string $draftId = null): void
    {
        $sku = trim($sku);
        if ($sku === '' || ! ($pct > 0)) {
            return;
        }
        $view = Temu2DataView::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->first()
            ?? new Temu2DataView(['sku' => $sku]);
        $value = is_array($view->value) ? $view->value : [];
        $value[self::KEY_PM_PCT] = round($pct, 2);
        $value[self::KEY_PM_PRICE] = round($price, 2);
        $value[self::KEY_PM_AT] = now()->toDateTimeString();
        $value[self::KEY_PM_ACTIVITY] = $activityId;
        if ($draftId !== null && $draftId !== '') {
            $value[self::KEY_PM_DRAFT] = $draftId;
        } else {
            unset($value[self::KEY_PM_DRAFT]);
        }
        $view->sku = $view->sku ?: $sku;
        $view->value = $value;
        $view->save();
    }

    /**
     * Stamp a failed New Temu Two push (the server job waits out a cooldown before retrying).
     */
    public static function markPushError(string $sku): void
    {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }
        $view = Temu2DataView::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->first()
            ?? new Temu2DataView(['sku' => $sku]);
        $value = is_array($view->value) ? $view->value : [];
        $value[self::KEY_PUSH_ERROR_AT] = now()->toDateTimeString();
        $value[self::KEY_PUSH_STATUS] = 'error';
        $view->sku = $view->sku ?: $sku;
        $view->value = $value;
        $view->save();
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
                            $view = new Temu2DataView(['sku' => $sku]);
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
            Log::error('New Temu Two suggested price persist failed: '.$e->getMessage());
        }
        $this->dirty = [];
    }

    /**
     * Wipe stored New Temu Two S PRC (the next server run writes it again).
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
     * Persist the exact S PRC dollar (full Temu price). SGROI is the live SPFT ÷ LP at that price.
     *
     * @param  list<string>  $labels
     */
    public function writeExactSprice(
        string $sku,
        float $sprice,
        float $lp = 0.0,
        float $ship = 0.0,
        float $sprcDil = 0.0,
        array $labels = [],
        bool $lmpAlert = false
    ): void {
        $sku = trim($sku);
        $sprice = round($sprice, 2);
        if ($sku === '' || ! ($sprice > 0)) {
            $this->clearSprice($sku);

            return;
        }
        $inverted = ($lp > 0)
            ? TemuShopifySalesService::newTemuoneSgroiAtSprice($sprice, $lp, $ship)
            : null;
        $sBase = TemuShopifySalesService::newTemuoneSBasePrice($sprice);
        $uncapped = $sprcDil > 0 ? round($sprcDil, 2) : $sprice;
        $cleanLabels = [];
        foreach ($labels as $label) {
            $label = trim((string) $label);
            if ($label !== '' && ! in_array($label, $cleanLabels, true)) {
                $cleanLabels[] = $label;
            }
        }
        $saved = $this->savedValue($sku);
        $fp = (string) ($saved[self::KEY_FINGERPRINT] ?? '');
        $this->queueWrite($sku, [
            'sgroi' => $inverted !== null ? round($inverted, 2) : null,
            'sprice' => $sprice,
            's_base' => $sBase > 0 ? $sBase : 0.0,
            'sprc_dil' => $uncapped,
            'labels' => $cleanLabels,
            'lmp_alert' => $lmpAlert,
            'capped' => abs($uncapped - $sprice) > 0.01,
            'use_saved' => true,
        ], $fp);
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
        // S Base Prc = S R (S PRC × 0.88), minus $2.99 under $30. Always derived from the saved S PRC,
        // so values saved under the old inversion never show or compare as the push base.
        $sBase = $sprice > 0
            ? TemuShopifySalesService::newTemuoneSBasePrice($sprice)
            : (is_numeric($saved[self::KEY_S_BASE] ?? null) ? round((float) $saved[self::KEY_S_BASE], 2) : 0.0);
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
                $pushedBase = TemuShopifySalesService::newTemuoneSBasePrice($full);
            }
        }
        $matched = $pushedBase > 0 && $sBase > 0 && abs($pushedBase - $sBase) < 0.015;
        if (! $matched && $sprice > 0 && is_numeric($saved['SPRICE_PUSHED_VALUE'] ?? null)) {
            $matched = abs(round((float) $saved['SPRICE_PUSHED_VALUE'], 2) - round($sprice, 2)) < 0.015;
        }
        $row['pushed_base'] = $pushedBase > 0 ? $pushedBase : null;
        $row['pushed_at'] = $saved[self::KEY_PUSHED_AT] ?? $saved['SPRICE_PUSHED_AT'] ?? null;
        $row['push_status'] = $matched ? 'pushed' : null;
        $row['push_error_at'] = null;
        if (! $matched && ($saved[self::KEY_PUSH_STATUS] ?? null) === 'error') {
            $row['push_status'] = 'error';
            $row['push_error_at'] = $saved[self::KEY_PUSH_ERROR_AT] ?? null;
        }
        $pmPct = is_numeric($saved[self::KEY_PM_PCT] ?? null) ? round((float) $saved[self::KEY_PM_PCT], 2) : 0.0;
        $row['pm_applied_pct'] = $pmPct > 0 ? $pmPct : null;
        $row['pm_applied_at'] = $pmPct > 0 ? ($saved[self::KEY_PM_AT] ?? null) : null;
        $row['pm_activity_id'] = $pmPct > 0 ? ($saved[self::KEY_PM_ACTIVITY] ?? null) : null;

        return $row;
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

    private function indexView(Temu2DataView $row): void
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
