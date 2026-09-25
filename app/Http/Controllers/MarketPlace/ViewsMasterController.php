<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Services\PricingErrorsFixCvrCacheBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Views Master — same SKU × channel rows as /pricing-errors-fix.
 * Only channels that store a views metric, and only rows with views > 0.
 */
class ViewsMasterController extends Controller
{
    /**
     * PEF pull keys that store a real views metric.
     * Best Buy, Shopify B2B, Purchasing Power, Shein, Faire, and AliExpress
     * do not track views on /pricing-errors-fix.
     *
     * @var array<string, string>
     */
    public const CHANNELS = [
        'amazon' => 'Amazon',
        'ebay' => 'eBay 1',
        'ebay2' => 'eBay 2',
        'ebay3' => 'eBay 3',
        'temu' => 'Temu',
        'temu2' => 'Temu 2',
        'doba' => 'Doba',
        'tiktok' => 'TikTok 1',
        'tiktok2' => 'TikTok 2',
        'macy' => "Macy's",
        'reverb' => 'Reverb',
        'topdawg' => 'TopDawg',
        'sb2c' => 'Shopify B2C',
    ];

    public function index(): View
    {
        $channels = [];
        foreach (self::CHANNELS as $key => $label) {
            $channels[] = ['key' => $key, 'label' => $label];
        }

        return view('market-places.views_master_view', [
            'channels' => $channels,
        ]);
    }

    public function dataJson(Request $request): JsonResponse
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        try {
            $wanted = $this->wantedKeys($request);
            $built = app(PricingErrorsFixCvrCacheBuilder::class)->build($wanted, null, true);

            $rows = [];
            $present = [];
            foreach ($built['rows'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $pull = strtolower((string) ($row['pull_key'] ?? ''));
                if (! isset(self::CHANNELS[$pull])) {
                    continue;
                }
                $views = $row['views'] ?? null;
                if (! is_numeric($views) || (float) $views <= 0) {
                    continue;
                }
                $rows[] = $row;
                $present[$pull] = self::CHANNELS[$pull];
            }

            $channels = [];
            foreach (self::CHANNELS as $key => $label) {
                if (isset($present[$key])) {
                    $channels[] = ['key' => $key, 'label' => $label];
                }
            }

            return response()->json([
                'data' => $rows,
                'channels' => $channels,
                'meta' => [
                    'total' => count($rows),
                    'channels' => array_column($channels, 'key'),
                    'errors' => $built['errors'] ?? [],
                    'source' => 'pricing-errors-fix',
                ],
            ])->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            Log::error('Views Master dataJson: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to fetch views data',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array<int, string>
     */
    private function wantedKeys(Request $request): array
    {
        $all = array_keys(self::CHANNELS);
        $raw = trim((string) $request->query('channel', ''));
        if ($raw === '') {
            return $all;
        }
        $wanted = array_values(array_filter(array_map(
            static fn ($k) => strtolower(trim((string) $k)),
            explode(',', $raw)
        )));
        $wanted = array_values(array_intersect($wanted, $all));

        return $wanted !== [] ? $wanted : $all;
    }
}
