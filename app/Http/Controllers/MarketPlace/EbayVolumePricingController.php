<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\MarketplacePercentage;
use App\Services\Ebay1VolumePricingService;
use App\Services\ShippingSlabRateService;
use App\Support\EbayVolumePricingRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EbayVolumePricingController extends Controller
{
    public function index()
    {
        return view('market-places.ebay1_volume_pricing', [
            'ebayTakeHome' => MarketplacePercentage::takeHomeDecimal('Ebay'),
        ]);
    }

    public function shipSlabs(ShippingSlabRateService $slabs): JsonResponse
    {
        try {
            $bands = $slabs->actWeightShipBands();
        } catch (\Throwable $e) {
            $bands = [];
        }

        return response()->json([
            'bands' => $bands,
            'take_home' => MarketplacePercentage::takeHomeDecimal('Ebay'),
        ]);
    }

    public function rules(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'rules' => $this->loadRules(),
        ]);
    }

    public function saveRules(Request $request): JsonResponse
    {
        $before = $this->loadRules();
        $rules = $this->storeRules($request->all(), false);

        $message = $rules['enabled'] ? 'Volume pricing rules are on.' : 'Volume pricing rules are off.';
        $started = false;
        if ($rules['enabled'] && $rules['auto_push'] && $this->rulesChanged($before, $rules)) {
            $started = $this->startAutoPush();
            $message .= $started
                ? ' Rules changed, so the new Buy 2, Buy 3, and Buy 4 are being pushed to eBay in the background.'
                : ' Rules changed, but the background push could not start. It will run at the next 5:30 AM or 9:30 PM IST.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'auto_push_started' => $started,
            'rules' => $rules,
        ]);
    }

    /**
     * Did anything that decides a discount change? Promotion ids and the saved fingerprint do not count.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function rulesChanged(array $before, array $after): bool
    {
        $pick = static fn (array $r): string => json_encode([
            $r['enabled'] ?? false,
            $r['auto_push'] ?? false,
            $r['weight'] ?? [],
            $r['dil'] ?? [],
            $r['npft'] ?? [],
        ]);

        return $pick($before) !== $pick($after);
    }

    /**
     * Run the same command the scheduler runs, detached, so it keeps going after this request ends and
     * the page does not have to stay open.
     */
    private function startAutoPush(): bool
    {
        try {
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                return false;
            }
            $log = storage_path('logs/ebay1-volume-pricing-auto-push.log');
            $cmd = 'nohup '.escapeshellarg($php).' '.escapeshellarg(base_path('artisan'))
                .' ebay1:volume-pricing-auto-push >> '.escapeshellarg($log).' 2>&1 &';
            exec($cmd);

            return true;
        } catch (\Throwable $e) {
            Log::warning('eBay1 volume pricing auto push could not start', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function push(Request $request, Ebay1VolumePricingService $service): JsonResponse
    {
        $payload = $request->all();
        $rules = $this->storeRules($payload['rules'] ?? $this->loadRules(), true);
        if (! $rules['enabled']) {
            return response()->json([
                'success' => false,
                'message' => 'Turn the volume pricing rules on before pushing to eBay.',
            ], 422);
        }

        $rows = $request->input('rows', []);
        if (! is_array($rows) || $rows === []) {
            return response()->json([
                'success' => false,
                'message' => 'No listings to push.',
            ], 422);
        }

        @set_time_limit(180);
        $result = $service->push($rules, array_slice($rows, 0, 5000), ! $request->boolean('full'));
        $rules['promotion_ids'] = $result['promotion_ids'];
        $rules['promotion_state'] = $result['promotion_state'];
        $this->persist($rules);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function storeRules(array $raw, bool $keepIdsOnly): array
    {
        $existing = $this->loadRules();
        $rules = EbayVolumePricingRule::merge($raw);
        $rules['promotion_ids'] = $existing['promotion_ids'] ?? [];
        $rules['promotion_state'] = $existing['promotion_state'] ?? [];
        if (! $keepIdsOnly) {
            $this->persist($rules);
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadRules(): array
    {
        return app(Ebay1VolumePricingService::class)->loadRules();
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function persist(array $rules): void
    {
        app(Ebay1VolumePricingService::class)->saveRules($rules);
    }
}
