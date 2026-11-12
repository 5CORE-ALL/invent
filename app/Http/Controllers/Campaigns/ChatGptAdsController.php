<?php

namespace App\Http\Controllers\Campaigns;

use App\Services\OpenAiAdsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ChatGPT Ads Tabulator page (/chatgpt-ads).
 *
 * Basic SKU columns come from the shared base; L30 delivery (impressions / clicks / spend) is merged in from the
 * OpenAI Advertiser API, matching the product-feed item_id to the SKU.
 */
class ChatGptAdsController extends AdsTabulatorController
{
    private const INSIGHT_DAYS = 30;

    protected function viewName(): string
    {
        return 'campaign.chatgpt.chatgpt-ads';
    }

    protected function channelKey(): string
    {
        return 'chatgpt_ads_tabulator';
    }

    protected function pageTitle(): string
    {
        return 'ChatGPT Ads';
    }

    protected function dataRouteName(): string
    {
        return 'chatgpt.ads.data';
    }

    /** @return list<array<string, mixed>> */
    protected function baseRows(): array
    {
        $rows = parent::baseRows();
        $bySku = $this->productInsights();

        foreach ($rows as &$row) {
            $m = $bySku[strtoupper(trim((string) $row['sku']))] ?? null;
            $impr = $m['impressions'] ?? null;
            $clicks = $m['clicks'] ?? null;
            $spend = $m['spend'] ?? null;
            $row['gpt_impressions'] = $impr;
            $row['gpt_clicks'] = $clicks;
            $row['gpt_spend'] = $spend !== null ? round($spend, 2) : null;
            $row['gpt_ctr'] = ($impr ?? 0) > 0 ? round(($clicks / $impr) * 100, 2) : null;
            $row['gpt_cpc'] = ($clicks ?? 0) > 0 ? round($spend / $clicks, 2) : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * Account-level L30 numbers + campaign list for the page header (and a quick way to see the API is connected).
     */
    public function summary(OpenAiAdsService $ads): JsonResponse
    {
        if (! $ads->isConfigured()) {
            return response()->json(['connected' => false, 'message' => 'ChatGPT Ads API key is not configured.'], 200);
        }

        try {
            $account = $ads->adAccount();
            $campaigns = $ads->campaigns();
            $insights = $ads->campaignInsights(self::INSIGHT_DAYS);
        } catch (\Throwable $e) {
            Log::warning('ChatGPT Ads summary failed: '.$e->getMessage());

            return response()->json(['connected' => false, 'message' => $e->getMessage()], 200);
        }

        $tot = ['impressions' => 0, 'clicks' => 0, 'spend' => 0.0];
        foreach ($insights as $r) {
            $tot['impressions'] += (int) ($r['campaign_impressions'] ?? $r['impressions'] ?? 0);
            $tot['clicks'] += (int) ($r['campaign_clicks'] ?? $r['clicks'] ?? 0);
            $tot['spend'] += (float) ($r['campaign_spend'] ?? $r['spend'] ?? 0);
        }

        return response()->json([
            'connected' => true,
            'account' => [
                'id' => $account['id'] ?? null,
                'name' => $account['account_name'] ?? $account['name'] ?? null,
                'currency' => $account['currency_code'] ?? 'USD',
                'timezone' => $account['timezone'] ?? null,
                'status' => $account['status'] ?? null,
            ],
            'campaigns' => count($campaigns),
            'days' => self::INSIGHT_DAYS,
            'impressions' => $tot['impressions'],
            'clicks' => $tot['clicks'],
            'spend' => round($tot['spend'], 2),
        ]);
    }

    /**
     * @return array<string, array{impressions:int, clicks:int, spend:float}>
     */
    private function productInsights(): array
    {
        $ads = app(OpenAiAdsService::class);
        if (! $ads->isConfigured()) {
            return [];
        }

        try {
            // Cached so a page refresh doesn't hit the API's 600/min limit; 10 minutes is fresh enough for L30.
            return Cache::remember('chatgpt_ads:product_insights:'.self::INSIGHT_DAYS, now()->addMinutes(10), fn () => $ads->productInsightsBySku(self::INSIGHT_DAYS));
        } catch (\Throwable $e) {
            Log::warning('ChatGPT Ads product insights failed: '.$e->getMessage());

            return [];
        }
    }
}
