<?php

namespace App\Services\CustomerCare;

use App\Models\CcReturnsPending;
use App\Models\ChannelMaster;
use App\Services\TikTok2ShopService;
use App\Services\TikTokShopService;
use App\Services\WalmartApiService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Live "pending returns" counts from each marketplace's own returns API.
 *
 * "Pending" = open returns that still need handling (new request awaiting the
 * seller, approved / in transit, or delivered and awaiting refund). Channel →
 * driver mapping is shared with the Messages Pending service.
 */
class MarketplacePendingReturnsService extends MarketplacePendingMessagesService
{
    public const NOTE_NO_RETURNS_API = 'No returns API wired';

    public function fetchAndStore(ChannelMaster $channel): array
    {
        $this->ensureSchema();

        try {
            $result = $this->fetch($channel);
        } catch (Throwable $e) {
            Log::warning('CC pending returns pull failed', [
                'channel_id' => $channel->id,
                'channel' => $channel->channel,
                'error' => $e->getMessage(),
            ]);
            $result = $this->makeResult(0, self::STATUS_ERROR, $e->getMessage(), null);
        }

        // A transient failure must not wipe a previously good count.
        $fetchedAt = now();
        if ($result['fetch_status'] === self::STATUS_ERROR) {
            $previous = CcReturnsPending::query()->where('channel_id', (int) $channel->id)->first();
            if ($previous !== null && (string) $previous->fetch_status === self::STATUS_OK) {
                $fetchedAt = $previous->last_fetched_at;
                $result = $this->makeResult(
                    (int) $previous->pending_count,
                    self::STATUS_OK,
                    'Stale — last refresh failed: '.($result['fetch_note'] ?? 'unknown error'),
                    $previous->source
                );
            }
        }

        $user = Auth::user();
        $row = CcReturnsPending::query()->updateOrCreate(
            ['channel_id' => (int) $channel->id],
            [
                'pending_count' => (int) $result['pending_count'],
                'fetch_status' => $result['fetch_status'],
                'fetch_note' => $result['fetch_note'],
                'source' => $result['source'],
                'last_fetched_at' => $fetchedAt,
                'updated_by_user_id' => $user?->id,
                'updated_by_name' => $user?->name,
            ]
        );

        return [
            'channel_id' => (int) $row->channel_id,
            'pending_count' => (int) $row->pending_count,
            'fetch_status' => (string) $row->fetch_status,
            'fetch_note' => $row->fetch_note,
            'source' => $row->source,
            'last_fetched_at' => optional($row->last_fetched_at)->toIso8601String(),
        ];
    }

    public function fetch(ChannelMaster $channel): array
    {
        $driver = $this->driverFor($channel);

        return match ($driver) {
            'ebay' => $this->fetchEbayReturns('ebay'),
            'ebay2' => $this->fetchEbayReturns('ebay2'),
            'ebay3' => $this->fetchEbayReturns('ebay3'),
            'walmart' => $this->fetchWalmartReturns(),
            'tiktok' => $this->fetchTikTokReturns(app(TikTokShopService::class), 'tiktok'),
            'tiktok2' => $this->fetchTikTokReturns(app(TikTok2ShopService::class), 'tiktok2'),
            'shopify' => $this->fetchShopifyReturns('shopify'),
            'shopify_b5c' => $this->fetchShopifyReturns('shopify_b5c'),
            'shopify_pls' => $this->fetchShopifyReturns('prolightsounds'),
            default => $this->noReturnsApi($this->slugFor($channel)),
        };
    }

    /**
     * eBay Post-Order API: countSummary gives per-state counts. Pending =
     * RETURN_STARTED (awaiting seller) + ITEM_DELIVERED (awaiting refund).
     *
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    protected function fetchEbayReturns(string $account): array
    {
        $token = $this->ebayToken($account);
        if ($token === '') {
            return $this->makeResult(0, self::STATUS_ERROR, 'eBay credentials are missing.', $account);
        }

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'IAF '.$token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US',
                ])
                ->timeout(15)
                ->connectTimeout(6)
                ->get('https://api.ebay.com/post-order/v2/return/search', [
                    'role' => 'SELLER',
                    'limit' => 1,
                ]);
        } catch (Throwable $e) {
            return $this->makeResult(0, self::STATUS_ERROR, $e->getMessage(), $account);
        }

        if (! $response->successful()) {
            $msg = $response->json('errors.0.message')
                ?? $response->json('errorMessage.error.0.message')
                ?? $response->json('message');

            return $this->makeResult(
                0,
                self::STATUS_ERROR,
                'eBay returns HTTP '.$response->status().(is_string($msg) && $msg !== '' ? ': '.$msg : ''),
                $account
            );
        }

        $summary = $response->json('countSummary');
        if (! is_array($summary)) {
            return $this->makeResult(0, self::STATUS_ERROR, 'eBay returns response had no countSummary.', $account);
        }

        $count = 0;
        foreach ($summary as $row) {
            $type = strtoupper((string) ($row['type'] ?? ''));
            if (in_array($type, ['RETURN_STARTED', 'ITEM_DELIVERED'], true)) {
                $count += max(0, (int) ($row['count'] ?? 0));
            }
        }

        return $this->makeResult($count, self::STATUS_OK, null, $account.':post-order.return');
    }

    /**
     * Walmart Returns API: INITIATED = created or in transit back (not yet
     * delivered / refunded). Looks back 120 days.
     *
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    protected function fetchWalmartReturns(): array
    {
        $token = app(WalmartApiService::class)->getAccessToken();
        if (! $token) {
            return $this->makeResult(0, self::STATUS_ERROR, 'Walmart credentials are missing.', 'walmart');
        }

        $base = rtrim((string) config('services.walmart.api_endpoint', 'https://marketplace.walmartapis.com'), '/');
        $base = (string) preg_replace('#/v3$#', '', $base);

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'WM_SEC.ACCESS_TOKEN' => $token,
                    'WM_QOS.CORRELATION_ID' => (string) \Illuminate\Support\Str::uuid(),
                    'WM_SVC.NAME' => 'Walmart Marketplace',
                    'Accept' => 'application/json',
                ])
                ->timeout(15)
                ->connectTimeout(6)
                ->get($base.'/v3/returns', [
                    'status' => 'INITIATED',
                    'returnCreationStartDate' => gmdate('Y-m-d', strtotime('-120 days')),
                    'limit' => 1,
                ]);
        } catch (Throwable $e) {
            return $this->makeResult(0, self::STATUS_ERROR, $e->getMessage(), 'walmart');
        }

        if (! $response->successful()) {
            return $this->makeResult(0, self::STATUS_ERROR, 'Walmart returns HTTP '.$response->status(), 'walmart');
        }

        $total = $response->json('meta.totalCount');
        if (! is_numeric($total)) {
            return $this->makeResult(0, self::STATUS_ERROR, 'Walmart returns response had no totalCount.', 'walmart');
        }

        return $this->makeResult((int) $total, self::STATUS_OK, null, 'walmart:returns');
    }

    /**
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    protected function fetchTikTokReturns(TikTokShopService $api, string $label): array
    {
        $resp = $api->getPendingReturnCount();
        if (empty($resp['success'])) {
            return $this->makeResult(0, self::STATUS_ERROR, (string) ($resp['message'] ?? 'TikTok returns pull failed.'), $label);
        }

        return $this->makeResult((int) ($resp['count'] ?? 0), self::STATUS_OK, null, $label.':returns');
    }

    /**
     * Shopify: orders whose aggregated return status is "requested" or
     * "in progress".
     *
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    protected function fetchShopifyReturns(string $configKey): array
    {
        $cfg = config('services.'.$configKey, []);
        $domain = preg_replace('#^https?://#', '', (string) ($cfg['domain'] ?? $cfg['store_url'] ?? ''));
        $domain = rtrim((string) $domain, '/');
        $token = (string) ($cfg['access_token'] ?? $cfg['password'] ?? '');
        if ($domain === '' || $token === '') {
            return $this->makeResult(0, self::STATUS_ERROR, 'Shopify credentials are missing.', $configKey);
        }

        $query = '{ ordersCount(query: "return_status:return_requested OR return_status:in_progress", limit: null) { count } }';

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(15)
                ->connectTimeout(6)
                ->post("https://{$domain}/admin/api/2024-10/graphql.json", ['query' => $query]);
        } catch (Throwable $e) {
            return $this->makeResult(0, self::STATUS_ERROR, $e->getMessage(), $configKey);
        }

        $json = $response->json() ?? [];
        $count = $json['data']['ordersCount']['count'] ?? null;
        if (is_numeric($count)) {
            return $this->makeResult((int) $count, self::STATUS_OK, null, $configKey.':orders.return_status');
        }

        $err = $json['errors'][0]['message'] ?? ('Shopify HTTP '.$response->status());

        return $this->makeResult(0, self::STATUS_ERROR, (string) $err, $configKey);
    }

    /**
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    protected function noReturnsApi(?string $source): array
    {
        return $this->makeResult(0, self::STATUS_UNSUPPORTED, self::NOTE_NO_RETURNS_API, $source);
    }

    public function ensureSchema(): void
    {
        if (Schema::hasTable('cc_returns_pending')) {
            return;
        }

        Schema::create('cc_returns_pending', function ($table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->unique();
            $table->unsignedInteger('pending_count')->default(0);
            $table->string('fetch_status', 32)->nullable();
            $table->string('fetch_note', 512)->nullable();
            $table->string('source', 64)->nullable();
            $table->timestamp('last_fetched_at')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->string('updated_by_name', 191)->nullable();
            $table->timestamps();
        });
    }
}
