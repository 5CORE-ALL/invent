<?php

namespace App\Http\Controllers\MarketPlace;

use App\Console\Commands\PullAmazonBuyboxCommand;
use App\Http\Controllers\Controller;
use App\Models\AmazonBuyboxData;
use App\Models\AmazonDatasheet;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AmzBuyboxController extends Controller
{
    public function index()
    {
        return view('market-places.amz_buybox');
    }

    /**
     * Tabulator JSON — Product Master rows + cached SP-API buy box columns.
     */
    public function data(Request $request)
    {
        $productMasters = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->orderBy('parent')
            ->orderBy('sku')
            ->get(['id', 'parent', 'sku', 'main_image']);

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyBySku = ShopifySku::mapByProductSkus($skus);

        $buyboxBySku = [];
        if (Schema::hasTable('amazon_buybox_data') && $skus !== []) {
            AmazonBuyboxData::query()
                ->whereIn('sku', array_map(static fn ($s) => strtoupper(trim((string) $s)), $skus))
                ->get()
                ->each(function ($row) use (&$buyboxBySku) {
                    $buyboxBySku[strtoupper(trim((string) $row->sku))] = $row;
                });
        }

        $asinBySku = [];
        $amzL30BySku = [];
        if ($skus !== []) {
            foreach (array_chunk($skus, 500) as $skuChunk) {
                AmazonDatasheet::query()
                    ->whereIn('sku', $skuChunk)
                    ->select('id', 'sku', 'asin', 'units_ordered_l30')
                    ->orderBy('id')
                    ->get()
                    ->each(function ($row) use (&$asinBySku, &$amzL30BySku) {
                        $key = strtoupper(trim((string) $row->sku));
                        if ($key === '') {
                            return;
                        }
                        if (! isset($asinBySku[$key]) && ! empty($row->asin)) {
                            $asinBySku[$key] = (string) $row->asin;
                        }
                        if (! isset($amzL30BySku[$key])) {
                            $amzL30BySku[$key] = (float) ($row->units_ordered_l30 ?? 0);
                        }
                    });
            }
        }

        $data = [];
        foreach ($productMasters as $pm) {
            $sku = trim((string) ($pm->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $skuKey = strtoupper($sku);
            $bb = $buyboxBySku[$skuKey] ?? null;
            $shopify = $shopifyBySku[$sku] ?? null;
            $inv = (float) ($shopify->inv ?? 0);
            // OV L30 = Shopify sold qty (same as amazon-tabulator L30)
            $ovL30 = (float) ($shopify->quantity ?? 0);
            $amzL30 = (float) ($amzL30BySku[$skuKey] ?? 0);
            $dilPct = $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0;

            $row = [
                'parent' => trim((string) ($pm->parent ?? '')),
                'sku' => $sku,
                'inv' => $inv,
                'ov_l30' => $ovL30,
                'dil_pct' => $dilPct,
                'amz_l30' => $amzL30,
                'image' => $pm->main_image ?: null,
                'asin' => $bb->asin ?? ($asinBySku[$skuKey] ?? null),
            ];

            $bbFields = [
                'item_condition', 'status',
                'total_offer_count', 'offer_count_amazon', 'offer_count_merchant',
                'list_price', 'competitive_price_threshold', 'suggested_lower_price_plus_shipping',
                'buybox_listing_price', 'buybox_landed_price', 'buybox_shipping', 'buybox_currency',
                'lowest_listing_price', 'lowest_landed_price', 'lowest_shipping', 'lowest_fulfillment_channel',
                'is_buy_box_winner', 'my_offer', 'is_fulfilled_by_amazon', 'is_featured_merchant',
                'is_prime', 'is_national_prime',
                'our_listing_price', 'our_shipping', 'our_landed_price', 'our_subcondition',
                'our_feedback_rating', 'our_feedback_count',
                'our_ship_min_hours', 'our_ship_max_hours', 'our_ships_from_country',
                'bb_seller_id', 'bb_is_fulfilled_by_amazon', 'bb_is_featured_merchant', 'bb_is_prime',
                'bb_listing_price', 'bb_shipping', 'bb_landed_price',
                'bb_feedback_rating', 'bb_feedback_count', 'bb_subcondition', 'bb_ships_from_country',
                'sales_rank', 'sales_rank_category', 'error_message',
            ];

            foreach ($bbFields as $field) {
                $row[$field] = $bb ? $bb->{$field} : null;
            }
            $row['fetched_at'] = $bb && $bb->fetched_at
                ? $bb->fetched_at->timezone('America/Los_Angeles')->format('Y-m-d H:i')
                : null;

            $data[] = $row;
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'sku_count' => count($data),
                'buybox_cached' => count($buyboxBySku),
                'refreshed_at' => now()->toDateTimeString(),
                'pull' => PullAmazonBuyboxCommand::status(),
            ],
        ]);
    }

    /**
     * Start background cron: amazon:pull-buybox in lots of 40 (INV ≥ 1 only).
     */
    public function startPull(Request $request)
    {
        if (! Schema::hasTable('amazon_buybox_data')) {
            return response()->json([
                'success' => false,
                'error' => 'Table amazon_buybox_data missing. Run migrations.',
            ], 500);
        }

        $status = PullAmazonBuyboxCommand::status();
        $probe = Cache::lock(PullAmazonBuyboxCommand::LOCK_CACHE_KEY, 5);
        $lockFree = $probe->get();
        if ($lockFree) {
            $probe->release();
        }
        if (! empty($status['running']) && ! $lockFree) {
            return response()->json([
                'success' => true,
                'already_running' => true,
                'status' => $status,
                'message' => $status['message'] ?? 'Buy Box pull already running',
            ]);
        }
        // Stale "running" flag with no lock — allow a fresh start
        if (! empty($status['running']) && $lockFree) {
            PullAmazonBuyboxCommand::writeStatus([
                'running' => false,
                'message' => 'Previous pull flag cleared (stale)',
            ]);
        }

        $skus = $request->input('skus', []);
        if (is_string($skus)) {
            $decoded = json_decode($skus, true);
            $skus = is_array($decoded) ? $decoded : (preg_split('/[\s,]+/', $skus) ?: []);
        }
        if (! is_array($skus)) {
            $skus = [];
        }
        $skus = array_values(array_unique(array_filter(array_map(static function ($s) {
            return trim((string) $s);
        }, $skus))));

        // Pre-mark running so UI polling sees activity immediately
        PullAmazonBuyboxCommand::writeStatus([
            'running' => true,
            'total' => 0,
            'done' => 0,
            'ok' => 0,
            'fail' => 0,
            'lot' => 40,
            'started_at' => now()->toDateTimeString(),
            'finished_at' => null,
            'message' => 'Starting background Buy Box pull (lots of 40, INV ≥ 1)…',
        ]);

        $php = PHP_BINARY ?: 'php';
        $artisan = base_path('artisan');
        $args = [$php, $artisan, 'amazon:pull-buybox', '--lot=40'];

        // Write SKUs one-per-line (SKUs contain spaces — never pass as comma+whitespace CLI).
        $skusFile = null;
        if ($skus !== []) {
            $dir = storage_path('app/buybox-pull');
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $skusFile = $dir.'/skus-'.uniqid('', true).'.txt';
            file_put_contents($skusFile, implode("\n", $skus)."\n");
            $args[] = '--skus-file='.$skusFile;
        }

        $cmd = implode(' ', array_map('escapeshellarg', $args));
        $logFile = storage_path('logs/amazon-buybox-pull.log');
        // Remove temp SKUs file after the artisan process exits
        $cleanup = $skusFile ? ('; rm -f '.escapeshellarg($skusFile)) : '';
        $full = '('.$cmd.';'.$cleanup.') >> '.escapeshellarg($logFile).' 2>&1 &';

        try {
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                pclose(popen('start /B '.$cmd.' >> '.escapeshellarg($logFile).' 2>&1', 'r'));
            } else {
                exec($full);
            }
        } catch (\Throwable $e) {
            Log::error('AmzBuybox startPull spawn failed', ['error' => $e->getMessage()]);
            if ($skusFile && is_file($skusFile)) {
                @unlink($skusFile);
            }
            PullAmazonBuyboxCommand::writeStatus([
                'running' => false,
                'message' => 'Failed to start: '.$e->getMessage(),
                'finished_at' => now()->toDateTimeString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to start background pull: '.$e->getMessage(),
            ], 500);
        }

        Log::info('AmzBuybox background pull started', [
            'sku_filter_count' => count($skus),
            'skus_file' => $skusFile,
            'cmd' => $cmd,
        ]);

        return response()->json([
            'success' => true,
            'message' => $skus === []
                ? 'Background pull started for all SKUs with INV ≥ 1 (40 per lot)'
                : ('Background pull started for '.count($skus).' selected SKU(s); INV < 1 omitted; 40 per lot'),
            'status' => PullAmazonBuyboxCommand::status(),
        ]);
    }

    /**
     * Poll background pull progress.
     */
    public function pullStatus()
    {
        return response()->json([
            'success' => true,
            'status' => PullAmazonBuyboxCommand::status(),
        ]);
    }
}
