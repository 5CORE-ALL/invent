<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Jobs\RunChannelPushPrcJob;
use App\Models\ChannelTabulatorColumnSetting;
use App\Services\ChannelPromoPricingService;
use App\Services\Support\ChannelPushPrcJobStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChannelPromoPricingController extends Controller
{
    /** Channels that support background Push Prc queue (Std listing + sale + coupon). */
    private const PUSH_QUEUE_CHANNELS = ['ebay1', 'ebay2', 'ebay2op', 'ebay3'];

    public function __construct(
        private readonly ChannelPromoPricingService $promo
    ) {}

    /**
     * Queue Push Prc jobs (background). Appends if a job is already running.
     * Same pattern as /amazon-push-prc.
     */
    public function queuePushPrc(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_QUEUE_CHANNELS, true) || ! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel for Push Prc queue'], 422);
        }

        $items = $request->input('items', []);
        if (! is_array($items) || $items === []) {
            return response()->json(['success' => false, 'message' => 'No items to push'], 400);
        }

        $tasks = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $tasks[] = [
                'sku' => $item['sku'] ?? null,
                'std' => $item['std'] ?? $item['price'] ?? null,
                'sale' => $item['sale'] ?? $item['sale_price'] ?? null,
                'max' => $item['max'] ?? $item['max_price'] ?? null,
                'min' => $item['min'] ?? $item['min_price'] ?? null,
                'business' => $item['business'] ?? $item['business_price'] ?? null,
                'effective' => $item['effective'] ?? $item['std'] ?? $item['price'] ?? null,
                'prmt' => $item['prmt'] ?? $item['prmt_pct'] ?? 0,
                'cpn' => $item['cpn'] ?? $item['cpn_pct'] ?? 0,
                'cvr_disc' => $item['cvr_disc'] ?? $item['cvrDisc'] ?? 0,
            ];
        }

        $store = ChannelPushPrcJobStore::for($channel);
        $result = $store->createOrAppend($tasks);
        $state = $result['state'];
        $mode = $result['mode'];
        if ((int) ($state['total'] ?? 0) === 0) {
            return response()->json(['success' => false, 'message' => 'No valid push items (need SKU + Std > 0)'], 400);
        }

        $this->releaseUniqueJobLock($channel);
        $spawned = $this->spawnPushPrcWorker($channel);
        if (! $spawned) {
            try {
                RunChannelPushPrcJob::dispatch($channel);
                Log::warning('Channel Push Prc sync spawn failed — fell back to queue dispatch', ['channel' => $channel]);
            } catch (\Throwable $e) {
                Log::error('Channel Push Prc queue dispatch also failed', [
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $store->update(function (array $s) use ($spawned, $mode) {
            $s['worker_spawned_at'] = now()->toDateTimeString();
            if ($mode === 'append') {
                $s['last_message'] = $spawned
                    ? ('Appended — worker continuing ('.$s['total'].' total)…')
                    : ('Appended — waiting for worker ('.$s['total'].' total)');
            } else {
                $s['last_message'] = $spawned
                    ? ('Worker started — pushing '.$s['total'].' SKU(s)…')
                    : ('Queued — waiting for worker (run: php artisan channel:push-prc-run '.$s['channel'].' --sync)');
            }

            return $s;
        });

        $api = $store->toApiResponse($store->load());

        return response()->json(array_merge($api, [
            'success' => true,
            'mode' => $mode,
            'worker_spawned' => $spawned,
            'message' => $mode === 'append'
                ? ('Added to running Push Prc queue ('.$api['total'].' total).')
                : ('Push Prc started in background ('.$api['total'].' SKU(s)). You can refresh or queue more.'),
        ]));
    }

    public function pushPrcJobStatus(string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_QUEUE_CHANNELS, true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $store = ChannelPushPrcJobStore::for($channel);
        $state = $store->load();

        if ($store->isActive($state) && $store->isStale($state, 180) && ! $this->runnerLockHeld($channel)) {
            $this->releaseUniqueJobLock($channel);
            $kicked = $this->spawnPushPrcWorker($channel);
            $store->update(function (array $s) use ($kicked) {
                $s['last_message'] = $kicked
                    ? 'Worker re-started after stall — continuing Push Prc…'
                    : 'Push Prc stalled — could not start worker. Cancel and retry, or run: php artisan channel:push-prc-run --sync';
                $s['worker_spawned_at'] = now()->toDateTimeString();

                return $s;
            });
            $state = $store->load();
        }

        return response()->json($store->toApiResponse($state));
    }

    public function cancelPushPrc(string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_QUEUE_CHANNELS, true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $store = ChannelPushPrcJobStore::for($channel);
        $job = $store->forceStop('Cancelled by user.');
        $this->releaseUniqueJobLock($channel);

        return response()->json(array_merge($store->toApiResponse($job), [
            'success' => true,
            'message' => 'Push Prc cancelled.',
        ]));
    }

    private function spawnPushPrcWorker(string $channel): bool
    {
        try {
            if ($this->runnerLockHeld($channel)) {
                return true;
            }
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $artisan = base_path('artisan');
            $log = storage_path('logs/'.$channel.'-push-prc.log');
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' channel:push-prc-run '.escapeshellarg($channel).' --sync', 'r'));

                return true;
            }
            $cmd = 'nohup '.escapeshellarg($php).' '.escapeshellarg($artisan)
                .' channel:push-prc-run '.escapeshellarg($channel)
                .' --sync >> '.escapeshellarg($log).' 2>&1 &';
            exec($cmd);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Channel Push Prc worker spawn failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function runnerLockHeld(string $channel): bool
    {
        $lockPath = storage_path('app/'.$channel.'-push-prc/runner.lock');
        if (! is_file($lockPath)) {
            return false;
        }
        $h = @fopen($lockPath, 'c+');
        if (! $h) {
            return false;
        }
        $got = flock($h, LOCK_EX | LOCK_NB);
        if ($got) {
            flock($h, LOCK_UN);
        }
        fclose($h);

        return ! $got;
    }

    private function releaseUniqueJobLock(string $channel): void
    {
        try {
            \Illuminate\Support\Facades\Cache::lock(
                'laravel_unique_job:'.RunChannelPushPrcJob::class.':'.$channel.'-push-prc'
            )->forceRelease();
        } catch (\Throwable) {
            // ignore
        }
    }

    public function save(Request $request): JsonResponse
    {
        $channel = strtolower(trim((string) $request->input('channel', '')));
        $sku = trim((string) $request->input('sku', ''));

        if ($channel === '' || ! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU required'], 422);
        }

        $fields = [];
        foreach (['prmt_pct', 'cpn_pct', 'dsc_pct', 'dsc', 'appr', 'push_prc_status', 'push_prc_value'] as $key) {
            if ($request->exists($key)) {
                $fields[$key] = $request->input($key);
            }
        }
        if ($request->boolean('record_push_prc')) {
            $fields['push_prc_status'] = 'pushed';
            if ($request->exists('push_prc_value')) {
                $fields['push_prc_value'] = $request->input('push_prc_value');
            }
            $fields['push_prc_pushed_at'] = now();
        }

        if ($fields === []) {
            return response()->json(['success' => false, 'message' => 'No fields to save'], 422);
        }

        try {
            // Writes Amazon-format keys into the channel's *_data_view.value
            // (e.g. ebay1 → ebay_data_view: PEF_PRMT_PCT, PEF_CPN_PCT, PUSH_PRC_*)
            $saved = $this->promo->upsert($channel, $sku, $fields);
            $prmt = $this->nullablePct($saved['prmt_pct'] ?? null);
            $cpn = $this->nullablePct($saved['cpn_pct'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Promo pricing saved',
                'channel' => $channel,
                'sku' => $sku,
                'prmt_pct' => $prmt,
                'cpn_pct' => $cpn,
                'dsc' => $this->nullablePct($saved['dsc'] ?? null),
                'appr' => (bool) ($saved['appr'] ?? false),
                'PUSH_PRC_STATUS' => $saved['PUSH_PRC_STATUS'] ?? null,
                'PUSH_PRC_VALUE' => isset($saved['PUSH_PRC_VALUE']) && is_numeric($saved['PUSH_PRC_VALUE'])
                    ? round((float) $saved['PUSH_PRC_VALUE'], 2)
                    : null,
                '_prmt_pct_applied' => is_numeric($prmt) ? (float) $prmt : 0,
                '_cpn_pct_applied' => is_numeric($cpn) ? (float) $cpn : 0,
                '_dsc_applied' => is_numeric($saved['dsc'] ?? null) ? (float) $saved['dsc'] : 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('ChannelPromoPricing save failed', [
                'channel' => $channel,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Save failed'], 500);
        }
    }

    public function dilPrmtRules(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        return $this->loadRules(
            $channel.'_dil_vs_prmt',
            $this->defaultDilPrmtRules(),
            'prmt'
        );
    }

    public function saveDilPrmtRules(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $incoming = $request->input('rules');
        if (! is_array($incoming)) {
            return response()->json(['success' => false, 'message' => 'rules array required'], 422);
        }

        $rules = $this->persistRules(
            $channel.'_dil_vs_prmt',
            $this->defaultDilPrmtRules(),
            $incoming,
            'prmt'
        );

        return response()->json(['success' => true, 'channel' => $channel, 'rules' => $rules]);
    }

    public function cvrCpnRules(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        return $this->loadRules(
            $channel.'_cvr_vs_cpn',
            $this->defaultCvrCpnRules(),
            'cpn'
        );
    }

    public function saveCvrCpnRules(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $incoming = $request->input('rules');
        if (! is_array($incoming)) {
            return response()->json(['success' => false, 'message' => 'rules array required'], 422);
        }

        $rules = $this->persistRules(
            $channel.'_cvr_vs_cpn',
            $this->defaultCvrCpnRules(),
            $incoming,
            'cpn'
        );

        return response()->json(['success' => true, 'channel' => $channel, 'rules' => $rules]);
    }

    /**
     * Same slabs as PEF_DIL_PRMT_DEFAULTS / pefDefaultDilPrmtRules.
     *
     * @return list<array{key:string,label:string,prmt:float|int}>
     */
    private function defaultDilPrmtRules(): array
    {
        return [
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
    }

    /**
     * Same slabs as PEF_CVR_CPN_DEFAULTS / pefDefaultCvrCpnRules.
     *
     * @return list<array{key:string,label:string,cpn:float|int}>
     */
    private function defaultCvrCpnRules(): array
    {
        return [
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
    }

    /**
     * @param  list<array{key:string,label:string}>  $defaults
     */
    private function loadRules(string $channelName, array $defaults, string $valueKey): JsonResponse
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', $channelName)
            ->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        if (! is_array($saved) || $saved === []) {
            return response()->json([
                'success' => true,
                'is_default' => true,
                'rules' => $defaults,
            ]);
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
            $raw = $byKey[$k][$valueKey] ?? null;
            // CVR disc historically accepted cpn as alias
            if ($valueKey === 'disc' && $raw === null) {
                $raw = $byKey[$k]['cpn'] ?? null;
            }
            $val = is_numeric($raw) ? (float) $raw : $def[$valueKey];
            $rules[] = [
                'key' => $k,
                'label' => $def['label'],
                $valueKey => $val,
            ];
        }

        return response()->json([
            'success' => true,
            'is_default' => false,
            'rules' => $rules,
        ]);
    }

    /**
     * @param  list<array{key:string,label:string}>  $defaults
     * @param  list<array<string, mixed>>  $incoming
     * @return list<array{key:string,label:string}>
     */
    private function persistRules(string $channelName, array $defaults, array $incoming, string $valueKey): array
    {
        $byKey = [];
        foreach ($incoming as $item) {
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
            $raw = $byKey[$k][$valueKey] ?? null;
            if ($valueKey === 'disc' && $raw === null) {
                $raw = $byKey[$k]['cpn'] ?? null;
            }
            $val = is_numeric($raw) ? round((float) $raw, 2) : (float) $def[$valueKey];
            if ($val < 0) {
                $val = 0;
            }
            $rules[] = [
                'key' => $k,
                'label' => $def['label'],
                $valueKey => $val,
            ];
        }

        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => $channelName],
            ['visibility' => $rules, 'column_order' => array_column($rules, 'key')]
        );

        return $rules;
    }

    private function nullablePct(mixed $val): ?float
    {
        if ($val === null || $val === '' || ! is_numeric($val)) {
            return null;
        }

        return round((float) $val, 2);
    }
}
