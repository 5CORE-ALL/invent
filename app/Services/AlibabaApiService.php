<?php

namespace App\Services;

use App\Models\AlibabaMetric;

/**
 * Alibaba.com Open Platform — same IOP/REST signing model as AliExpress.
 *
 * Product list, order, inventory, and price methods are inherited from
 * AliExpressApiService (solution.* APIs). Override individual methods here
 * when Alibaba Open Platform method names diverge.
 */
class AlibabaApiService extends AliExpressApiService
{
    protected string $channelLabel = 'Alibaba';

    protected string $tokenEnvKey = 'ALIBABA_ACCESS_TOKEN';

    public function __construct()
    {
        parent::__construct();

        $this->appKey = (string) (config('services.alibaba.app_key') ?: '');
        $this->appSecret = (string) (config('services.alibaba.app_secret') ?: '');
        $this->accessToken = config('services.alibaba.access_token');

        $base = (string) (config('services.alibaba.api_base') ?: 'https://openapi.alibaba.com');
        $this->apiBase = str_ends_with($base, '/sync') ? $base : rtrim($base, '/').'/sync';
        $this->signPath = '/sync';
        $this->tokenParam = 'access_token';

        $gw = strtolower((string) (config('services.alibaba.gateway') ?: 'rest'));
        $this->gateway = in_array($gw, ['sync', 'rest'], true) ? $gw : 'rest';
        $this->restBase = rtrim((string) (config('services.alibaba.rest_base') ?: 'https://api-sg.aliexpress.com/rest'), '/');
        $rsm = strtolower((string) (config('services.alibaba.rest_sign_method') ?: 'hmac'));
        $this->restSignMethod = in_array($rsm, ['hmac', 'md5'], true) ? $rsm : 'hmac';
        $this->httpConnectTimeout = max(5, (int) (config('services.alibaba.connect_timeout') ?: 30));
        $this->httpTimeout = max(10, (int) (config('services.alibaba.timeout') ?: 60));
        $proxy = config('services.alibaba.http_proxy');
        $this->httpProxy = is_string($proxy) && $proxy !== '' ? $proxy : null;
        $this->resolveIpv4 = filter_var(
            config('services.alibaba.resolve_ipv4', true),
            FILTER_VALIDATE_BOOL
        );
    }

    /**
     * Alibaba.com ICBU unread / undealt messages, then AliExpress-style message APIs.
     *
     * @return array{success: bool, count: int, message?: string}
     */
    public function getPendingMessageCount(): array
    {
        $methods = [
            'alibaba.icbu.message.count',
            'alibaba.icbu.msg.unread.count',
            'alibaba.icbu.messagebox.count',
        ];
        foreach ($methods as $method) {
            $raw = $this->debugCallRest($method, []);
            $json = is_array($raw['response']['json'] ?? null) ? $raw['response']['json'] : [];
            $count = $this->extractPendingMessageTotal($json);
            if ($count !== null) {
                return ['success' => true, 'count' => $count];
            }
        }

        return parent::getPendingMessageCount();
    }

    protected function channelImageMetricsMarketplaceKey(): string
    {
        return 'alibaba';
    }

    /**
     * @return object{sku?: mixed, product_id?: mixed}|null
     */
    protected function findChannelMetricRow(string $trim): ?object
    {
        $row = AlibabaMetric::query()
            ->where('sku', $trim)
            ->orWhere('sku', strtoupper($trim))
            ->orWhere('sku', strtolower($trim))
            ->first();
        if ($row) {
            return $row;
        }

        return AlibabaMetric::query()->where('product_id', $trim)->first();
    }

    protected function findChannelProductIdFromDataView(string $trim): ?string
    {
        return null;
    }
}
