<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Models\ChannelTabulatorColumnSetting;

/**
 * eBay1 Dil vs PRMT sale-event auto-apply is disabled.
 * run() is a no-op so leftover artisan calls cannot create PEF SALE events.
 */
class PefDilPrmtAutoApplyService
{

    /**
     * @return list<array{key:string,label:string,prmt:float}>
     */
    public function loadRules(): array
    {
        $defaults = ChannelPromoPricingController::sharedDilPrmtDefaults();
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', ChannelPromoPricingController::DIL_PRMT_SHARED_STORE)
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
        return ChannelPromoPricingController::sharedDilPrmtSlabKey($dil);
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
        $log('eBay1 sale-event auto-apply is disabled — no Promotion API calls');

        return [
            'candidates' => 0,
            'ok' => 0,
            'failed' => 0,
            'paused' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
    }
}
