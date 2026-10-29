<?php

namespace App\Console\Commands;

use App\Models\DobaDailyData;
use App\Models\MarketplaceSyncSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only: shows where Doba order sync stops (Doba API, our table, Shopify import, queue, logs).
 * Writes nothing.
 */
class DiagnoseDobaOrders extends Command
{
    protected $signature = 'doba:diagnose-orders {--days=3 : Days the live Doba API probe should look back}';

    protected $description = 'Read-only: find why Doba orders are not syncing to the app / Shopify';

    public function handle(): int
    {
        $this->line('== 1. Settings');
        $this->line('  fetch_orders        : '.(MarketplaceSyncSettings::canFetchOrders('doba') ? 'ON' : 'OFF  <-- order fetch is disabled'));
        $this->line('  auto_import_shopify : '.(MarketplaceSyncSettings::canAutoImportToShopify('doba') ? 'ON' : 'OFF  <-- Shopify import is disabled'));
        $this->line('  MM_PAUSED_SHOPIFY_IMPORTS env: "'.(string) env('MM_PAUSED_SHOPIFY_IMPORTS', '').'"');
        $this->line('  Doba keys configured: '.((filled(config('services.doba.app_key')) && filled(config('services.doba.private_key'))) ? 'yes' : 'NO  <-- DOBA_APP_KEY / DOBA_PRIVATE_KEY missing'));

        $this->line('');
        $this->line('== 2. Our table (doba_daily_data)');
        if (! Schema::hasTable('doba_daily_data')) {
            $this->error('  table missing');

            return self::FAILURE;
        }
        $total = DobaDailyData::query()->count();
        $newestOrder = DobaDailyData::query()->max('order_time');
        $newestWrite = DobaDailyData::query()->max('updated_at');
        $newestCreate = DobaDailyData::query()->max('created_at');
        $this->line("  rows: {$total}");
        $this->line('  newest order_time : '.($newestOrder ?: '-'));
        $this->line('  newest created_at : '.($newestCreate ?: '-').'   (last time a NEW order was stored)');
        $this->line('  newest updated_at : '.($newestWrite ?: '-').'   (last time the fetch wrote anything)');
        foreach ([1, 3, 7, 14] as $d) {
            $n = DobaDailyData::query()->where('order_time', '>=', now()->subDays($d))->count();
            $this->line("  orders in last {$d} day(s): {$n}");
        }

        $this->line('');
        $this->line('== 3. Shopify import status (orders of the last 14 days)');
        $rows = DobaDailyData::query()
            ->where('order_time', '>=', now()->subDays(14))
            ->selectRaw("COALESCE(NULLIF(import_status,''),'(none)') as st, SUM(CASE WHEN shopify_order_id IS NOT NULL AND shopify_order_id != '' THEN 1 ELSE 0 END) as on_shopify, COUNT(*) as n")
            ->groupBy('st')
            ->get();
        foreach ($rows as $r) {
            $this->line(sprintf('  %-16s %4d rows, %4d have a Shopify order id', $r->st, $r->n, $r->on_shopify));
        }
        if ($rows->isEmpty()) {
            $this->line('  (no orders in the last 14 days)');
        }
        $newestShopify = DobaDailyData::query()->whereNotNull('shopify_order_id')->where('shopify_order_id', '!=', '')->max('order_time');
        $this->line('  newest order that reached Shopify: '.($newestShopify ?: '-'));

        $this->line('  newest 8 rows in our table:');
        $this->newestRows();

        $this->line('');
        $this->line('== 4. Queue');
        $this->workers();
        try {
            $queue = \App\Services\MarketplaceManager\MarketplaceManagerRegistry::queueFor('doba');
            $pending = DB::table('jobs')->where('queue', $queue)->count();
            $oldest = DB::table('jobs')->where('queue', $queue)->min('created_at');
            $this->line("  queue {$queue}: {$pending} waiting job(s)".($oldest ? ', oldest queued '.Carbon::createFromTimestamp((int) $oldest)->diffForHumans() : ''));
            if (Schema::hasTable('failed_jobs')) {
                $failed = DB::table('failed_jobs')->where('queue', $queue)->where('failed_at', '>=', now()->subDays(3))->count();
                $this->line("  failed jobs on {$queue} (last 3 days): {$failed}");
                $last = DB::table('failed_jobs')->where('queue', $queue)->orderByDesc('id')->first();
                if ($last) {
                    $this->line('  last failure: '.$last->failed_at.' '.mb_substr(strtok((string) $last->exception, "\n"), 0, 220));
                }
            }
        } catch (\Throwable $e) {
            $this->warn('  queue check failed: '.$e->getMessage());
        }

        $this->line('');
        $this->line('== 5. Live Doba API probe (read only)');
        $this->probeApi(max(1, (int) $this->option('days')));

        $this->line('');
        $this->line('== 6. Recent Doba lines in the log');
        $this->tailLog();

        return self::SUCCESS;
    }

    private function probeApi(int $days): void
    {
        $appKey = (string) config('services.doba.app_key');
        $privateKey = (string) config('services.doba.private_key');
        if ($appKey === '' || $privateKey === '') {
            $this->error('  keys missing');

            return;
        }
        $timestamp = (int) round(microtime(true) * 1000);
        $content = "appKey={$appKey}&signType=rsa2&timestamp={$timestamp}";
        $pem = "-----BEGIN RSA PRIVATE KEY-----\n".wordwrap($privateKey, 64, "\n", true)."\n-----END RSA PRIVATE KEY-----";
        $key = openssl_pkey_get_private($pem);
        if (! $key) {
            $this->error('  DOBA_PRIVATE_KEY is not a valid RSA private key');

            return;
        }
        openssl_sign($content, $signature, $key, OPENSSL_ALGO_SHA256);

        // Doba answers oldest -> newest, so walk every page of the window and look at the NEWEST rows.
        $all = [];
        $pageSize = 40;
        for ($page = 1; $page <= 10; $page++) {
            $timestamp = (int) round(microtime(true) * 1000);
            $content = "appKey={$appKey}&signType=rsa2&timestamp={$timestamp}";
            openssl_sign($content, $signature, $key, OPENSSL_ALGO_SHA256);
            try {
                $response = Http::withoutVerifying()->timeout(40)->withHeaders([
                    'appKey' => $appKey,
                    'signType' => 'rsa2',
                    'timestamp' => $timestamp,
                    'sign' => base64_encode($signature),
                    'Content-Type' => 'application/json',
                ])->post('https://openapi.doba.com/api/seller/queryOrderDetail', [
                    'pageNo' => $page,
                    'pageSize' => $pageSize,
                    'beginTime' => now()->subDays($days)->format('Y-m-d\TH:i:sP'),
                    'endTime' => now()->format('Y-m-d\TH:i:sP'),
                ]);
            } catch (\Throwable $e) {
                $this->error("  page {$page}: HTTP exception: ".$e->getMessage());
                break;
            }
            $json = $response->json();
            if (! is_array($json)) {
                $this->line("  page {$page}: HTTP ".$response->status().' body: '.mb_substr((string) $response->body(), 0, 300));
                break;
            }
            $bd = $json['businessData'] ?? null;
            $status = is_array($bd) ? ($bd['businessStatus'] ?? $bd[0]['businessStatus'] ?? null) : null;
            $msg = is_array($bd) ? ($bd['businessMessage'] ?? $bd[0]['businessMessage'] ?? null) : null;
            $data = is_array($bd) ? ($bd[0]['data'] ?? $bd['data'] ?? []) : [];
            $data = is_array($data) ? $data : [];
            $this->line("  page {$page}: HTTP ".$response->status().' code='.($json['responseCode'] ?? 'n/a').' status='.($status ?? 'n/a').' '.($msg ?? '').' rows='.count($data));
            $all = array_merge($all, $data);
            if (count($data) < $pageSize) {
                break;
            }
            usleep(600000);
        }

        $this->line('  total orders Doba returned for the last '.$days.' day(s): '.count($all));
        $missing = 0;
        foreach (array_slice($all, -14) as $order) {
            $no = (string) ($order['ordBusiId'] ?? $order['orderNo'] ?? '');
            $row = $no !== '' ? DobaDailyData::query()->where('order_no', $no)->first() : null;
            $missing += $row ? 0 : 1;
            $this->line(sprintf(
                '    %-22s %-20s %-14s %s',
                $no,
                (string) ($order['ordTime'] ?? $order['orderTime'] ?? ''),
                (string) ($order['ordStatus'] ?? $order['orderStatus'] ?? ''),
                $row ? 'in app, import='.($row->import_status ?: '(none)').', shopify='.($row->shopify_order_id ?: '-') : 'MISSING from app'
            ));
        }
        if ($missing > 0) {
            $this->warn("  {$missing} of the newest orders are missing from our table -> the fetch is not storing them.");
        }
    }

    private function newestRows(): void
    {
        $rows = DobaDailyData::query()->orderByDesc('order_time')->limit(8)->get();
        foreach ($rows as $r) {
            $this->line(sprintf(
                '  %-22s %-20s %-14s import=%-14s shopify=%s created=%s',
                $r->order_no,
                $r->order_time,
                $r->order_status,
                $r->import_status ?: '(none)',
                $r->shopify_order_id ?: '-',
                $r->created_at
            ));
        }
    }

    private function workers(): void
    {
        if (! function_exists('shell_exec')) {
            $this->line('  shell_exec disabled');

            return;
        }
        $out = (string) @shell_exec('ps aux 2>/dev/null | grep -E "queue:work|queue:listen|horizon|schedule:(run|work)" | grep -v grep');
        $lines = array_filter(array_map('trim', explode("\n", $out)));
        $this->line('  queue/scheduler processes: '.count($lines));
        $hasDoba = false;
        foreach ($lines as $l) {
            if (stripos($l, 'mm-doba') !== false || stripos($l, 'mm-') !== false) {
                $hasDoba = $hasDoba || stripos($l, 'mm-doba') !== false;
            }
            $this->line('    '.mb_substr(preg_replace('/\s+/', ' ', $l), 0, 200));
        }
        if (! $hasDoba) {
            $this->warn('  no worker command line mentions mm-doba (fine only if a catch-all worker/horizon handles that queue)');
        }
    }

    private function tailLog(): void
    {
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        if ($files === []) {
            $this->line('  no log file');

            return;
        }
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $file = $files[0];
        $size = filesize($file);
        $fh = fopen($file, 'rb');
        if (! $fh) {
            return;
        }
        fseek($fh, max(0, $size - 6 * 1024 * 1024));
        $text = (string) stream_get_contents($fh);
        fclose($fh);
        $hits = [];
        $runs = [];
        foreach (explode("\n", $text) as $line) {
            if (stripos($line, 'doba') !== false && preg_match('/\.(ERROR|WARNING|CRITICAL)/', $line)) {
                $hits[] = mb_substr($line, 0, 320);
            }
            if (stripos($line, 'SyncMarketplaceOrdersJob') !== false && stripos($line, '"marketplace":"doba"') !== false) {
                $runs[] = mb_substr($line, 0, 200);
            }
        }
        $this->line('  last Doba sync job runs: '.($runs === [] ? 'NONE in the last 6 MB of log (job is not running)' : ''));
        foreach (array_slice($runs, -6) as $line) {
            $this->line('    '.$line);
        }
        $hits = array_slice($hits, -12);
        if ($hits === []) {
            $this->line('  no Doba warnings/errors in the last 6 MB of '.basename($file));

            return;
        }
        foreach ($hits as $line) {
            $this->line('  '.$line);
        }
    }
}
