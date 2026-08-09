<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Re-pull fulfillment tracking from Shopify Admin API for shopify_raw_orders
 * rows that are missing tracking_number in a date window.
 *
 *   php artisan shopify:backfill-tracking --days=30
 *   php artisan shopify:backfill-tracking --days=30 --limit=500
 */
class BackfillShopifyTrackingNumbers extends Command
{
    protected $signature = 'shopify:backfill-tracking
        {--days=30 : Look back this many days on order_date / created_at}
        {--limit=0 : Max distinct Shopify order_ids to refresh (0 = all missing in range)}
        {--sleep-ms=350 : Pause between Shopify order API calls}';

    protected $description = 'Pull missing tracking numbers from Shopify fulfillments into shopify_raw_orders';

    public function handle(): int
    {
        if (! Schema::hasTable('shopify_raw_orders')) {
            $this->error('shopify_raw_orders table missing.');

            return self::FAILURE;
        }

        $store = preg_replace('#^https?://#i', '', trim((string) config('shopify.store_url', '')));
        $store = rtrim((string) $store, '/');
        $token = trim((string) config('shopify.access_token', ''));
        $apiVersion = trim((string) (config('shopify.api_version') ?: '2024-10'));

        if ($store === '' || $token === '') {
            $this->error('Missing SHOPIFY_STORE_URL or SHOPIFY_ACCESS_TOKEN.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $limit = max(0, (int) $this->option('limit'));
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $from = Carbon::now()->subDays($days)->startOfDay()->toDateString();

        $query = DB::table('shopify_raw_orders')
            ->where(function ($q) {
                $q->whereNull('tracking_number')->orWhere('tracking_number', '');
            })
            ->where(function ($q) use ($from) {
                $q->where('order_date', '>=', $from)
                    ->orWhere('created_at', '>=', $from.' 00:00:00');
            })
            ->whereNotNull('order_id')
            ->where('order_id', '>', 0)
            ->select('order_id')
            ->distinct()
            ->orderByDesc('order_id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $orderIds = $query->pluck('order_id')->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->values()->all();
        $total = count($orderIds);

        $this->info("Missing-tracking Shopify orders in last {$days} day(s): {$total}");
        if ($total === 0) {
            return self::SUCCESS;
        }

        $checked = 0;
        $withTracking = 0;
        $updatedRows = 0;
        $empty = 0;
        $errors = 0;

        foreach ($orderIds as $orderId) {
            $checked++;
            if ($checked === 1 || $checked % 25 === 0 || $checked === $total) {
                $this->line("  … {$checked}/{$total} (saved rows: {$updatedRows}, with TN: {$withTracking})");
            }

            try {
                $resp = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->get("https://{$store}/admin/api/{$apiVersion}/orders/{$orderId}.json");

                if ($resp->status() === 429) {
                    $this->warn('  Rate limited — sleeping 5s');
                    sleep(5);
                    $resp = Http::withHeaders([
                        'X-Shopify-Access-Token' => $token,
                        'Content-Type' => 'application/json',
                    ])->timeout(30)->get("https://{$store}/admin/api/{$apiVersion}/orders/{$orderId}.json");
                }

                if (! $resp->successful()) {
                    $errors++;
                    if ($sleepMs > 0) {
                        usleep($sleepMs * 1000);
                    }
                    continue;
                }

                $order = $resp->json('order') ?? [];
                [$trackingNumber, $trackingCompany, $trackingUrl] = $this->extractTracking($order);

                $payload = [
                    'fulfillment_status' => $order['fulfillment_status'] ?? null,
                    'updated_at' => now(),
                ];

                if ($trackingNumber !== null) {
                    $withTracking++;
                    $payload['tracking_number'] = $trackingNumber;
                    if ($trackingCompany !== null) {
                        $payload['tracking_company'] = $trackingCompany;
                    }
                    if ($trackingUrl !== null) {
                        $payload['tracking_url'] = $trackingUrl;
                    }
                    $updatedRows += (int) DB::table('shopify_raw_orders')
                        ->where('order_id', $orderId)
                        ->where(function ($q) {
                            $q->whereNull('tracking_number')->orWhere('tracking_number', '');
                        })
                        ->update($payload);
                } else {
                    $empty++;
                    DB::table('shopify_raw_orders')
                        ->where('order_id', $orderId)
                        ->update($payload);
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->warn("  order {$orderId}: ".$e->getMessage());
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->newLine();
        $this->info('Done.');
        $this->line("  Checked orders : {$checked}");
        $this->line("  Shopify had TN : {$withTracking}");
        $this->line("  Still empty    : {$empty}");
        $this->line("  Rows updated   : {$updatedRows}");
        $this->line("  Errors         : {$errors}");

        return self::SUCCESS;
    }

    /**
     * @return array{0:?string,1:?string,2:?string}
     */
    private function extractTracking(array $order): array
    {
        $fulfillments = is_array($order['fulfillments'] ?? null) ? $order['fulfillments'] : [];
        $best = null;

        foreach ($fulfillments as $f) {
            if (! is_array($f)) {
                continue;
            }
            $tn = null;
            if (! empty($f['tracking_numbers']) && is_array($f['tracking_numbers'])) {
                $parts = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $f['tracking_numbers'])));
                $tn = $parts !== [] ? implode(', ', $parts) : null;
            } elseif (! empty($f['tracking_number'])) {
                $tn = trim((string) $f['tracking_number']) ?: null;
            }
            if ($tn !== null) {
                $best = $f;
                $company = trim((string) ($f['tracking_company'] ?? '')) ?: null;
                $url = null;
                if (! empty($f['tracking_urls']) && is_array($f['tracking_urls'])) {
                    $url = trim((string) ($f['tracking_urls'][0] ?? '')) ?: null;
                } elseif (! empty($f['tracking_url'])) {
                    $url = trim((string) $f['tracking_url']) ?: null;
                }

                return [$tn, $company, $url];
            }
            if ($best === null) {
                $best = $f;
            }
        }

        return [null, null, null];
    }
}
