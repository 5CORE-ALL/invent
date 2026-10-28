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

        $this->line('');
        $this->line('== 4. Queue');
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

        try {
            $response = Http::withoutVerifying()->timeout(40)->withHeaders([
                'appKey' => $appKey,
                'signType' => 'rsa2',
                'timestamp' => $timestamp,
                'sign' => base64_encode($signature),
                'Content-Type' => 'application/json',
            ])->post('https://openapi.doba.com/api/seller/queryOrderDetail', [
                'pageNo' => 1,
                'pageSize' => 20,
                'beginTime' => now()->subDays($days)->format('Y-m-d\TH:i:sP'),
                'endTime' => now()->format('Y-m-d\TH:i:sP'),
            ]);
        } catch (\Throwable $e) {
            $this->error('  HTTP exception: '.$e->getMessage());

            return;
        }

        $this->line('  HTTP '.$response->status());
        $json = $response->json();
        if (! is_array($json)) {
            $this->line('  body: '.mb_substr((string) $response->body(), 0, 400));

            return;
        }
        $this->line('  responseCode: '.($json['responseCode'] ?? 'n/a').'  message: '.($json['responseMessage'] ?? ''));
        $bd = $json['businessData'] ?? null;
        $status = is_array($bd) ? ($bd['businessStatus'] ?? $bd[0]['businessStatus'] ?? null) : null;
        $msg = is_array($bd) ? ($bd['businessMessage'] ?? $bd[0]['businessMessage'] ?? null) : null;
        $this->line('  businessStatus: '.($status ?? 'n/a').'  '.($msg ?? ''));
        $data = is_array($bd) ? ($bd[0]['data'] ?? $bd['data'] ?? []) : [];
        $this->line('  orders returned for the last '.$days.' day(s) (page 1): '.count((array) $data));
        $known = 0;
        foreach (array_slice((array) $data, 0, 20) as $order) {
            $no = (string) ($order['ordBusiId'] ?? $order['orderNo'] ?? '');
            $inDb = $no !== '' && DobaDailyData::query()->where('order_no', $no)->exists();
            $known += $inDb ? 1 : 0;
            $this->line(sprintf('    %-22s %-20s %-18s %s', $no, (string) ($order['ordTime'] ?? $order['orderTime'] ?? ''), (string) ($order['ordStatus'] ?? $order['orderStatus'] ?? ''), $inDb ? 'in app' : 'MISSING from app'));
        }
        if ($data !== [] && $known < count((array) $data)) {
            $this->warn('  Doba has orders our table does not -> the fetch is not storing them.');
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
        foreach (explode("\n", $text) as $line) {
            if (stripos($line, 'doba') !== false && preg_match('/\.(ERROR|WARNING|CRITICAL)/', $line)) {
                $hits[] = mb_substr($line, 0, 320);
            }
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
