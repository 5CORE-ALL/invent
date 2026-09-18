<?php

namespace App\Services\Lqs;

use App\Models\AlibabaMetric;
use App\Models\AliexpressMetric;
use App\Models\DobaMetric;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\FaireMetric;
use App\Models\LqsMarketplaceAction;
use App\Models\LqsMarketplaceHistory;
use App\Models\LqsMarketplaceScore;
use App\Models\NeweggMetric;
use App\Models\ProductMaster;
use App\Models\ReverbMetric;
use App\Models\SheinMetric;
use App\Models\ShopifySku;
use App\Models\Temu2Metric;
use App\Models\TemuMetric;
use App\Models\WalmartListingViewsData;
use App\Models\WalmartMetrics;
use App\Support\Lqs\LqsMarketplaceCatalog;
use Illuminate\Support\Facades\Schema;

class LqsMarketplacePageService
{
    public function config(string $slug): array
    {
        $channel = LqsMarketplaceCatalog::find($slug);
        if (! $channel) {
            throw new \InvalidArgumentException('Unknown LQS marketplace: '.$slug);
        }

        return $channel;
    }

    public function tableRows(string $slug): array
    {
        $channel = $this->config($slug);
        $normalizeSku = static function ($value) {
            return strtoupper(str_replace("\u{00a0}", ' ', trim((string) $value)));
        };

        $productMastersBySku = ProductMaster::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->get()
            ->keyBy(fn ($row) => $normalizeSku($row->sku));

        $shopifyBySku = [];
        foreach (ShopifySku::all() as $shopifyRow) {
            $key = $normalizeSku($shopifyRow->sku);
            if ($key !== '' && ! isset($shopifyBySku[$key])) {
                $shopifyBySku[$key] = $shopifyRow;
            }
        }

        $scoresBySku = [];
        if (Schema::hasTable('lqs_marketplace_scores')) {
            foreach (LqsMarketplaceScore::query()->where('marketplace', $slug)->get() as $score) {
                $scoresBySku[$normalizeSku($score->sku)] = $score;
            }
        }

        $latestActionsBySku = collect();
        if (Schema::hasTable('lqs_marketplace_actions')) {
            $latestActionsBySku = LqsMarketplaceAction::query()
                ->where('marketplace', $slug)
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy(fn ($row) => $normalizeSku($row->sku))
                ->map(fn ($group) => $group->first());
        }

        $apiMetrics = $this->apiMetricsBySku($channel['metrics'] ?? null, $normalizeSku);
        $hasAuditColumns = Schema::hasColumn('lqs_marketplace_scores', 'audit_findings');
        $yoastBySku = $slug === 'shopify' ? $this->shopifySeoScoresBySku() : [];

        $rows = [];
        foreach ($productMastersBySku as $normalizedSku => $productMaster) {
            $shopifyRow = $shopifyBySku[$normalizedSku] ?? null;
            $score = $scoresBySku[$normalizedSku] ?? null;
            $api = $apiMetrics[$normalizedSku] ?? [];
            $yoast = $yoastBySku[$normalizedSku] ?? [];

            $inv = $shopifyRow ? (int) ($shopifyRow->inv ?? 0) : 0;
            $imageSrc = $shopifyRow ? ($shopifyRow->image_src ?? null) : null;
            $l30 = $this->firstNumber($api['l30'] ?? null, $score->l30 ?? null, $shopifyRow ? ($shopifyRow->quantity ?? null) : null);
            $sessions = $this->firstNumber($api['sessions'] ?? null, $score->sessions ?? null, $shopifyRow ? ($shopifyRow->views ?? null) : null);
            $price = $this->firstNumber($api['price'] ?? null, $score->price ?? null);
            $listingId = $yoast['shopify_product_id'] ?? ($api['listing_id'] ?? ($score->listing_id ?? null));
            $lqs = $this->firstNullableNumber($score->lqs ?? null, $api['lqs'] ?? null);
            if ($lqs === null && isset($yoast['seo_score']) && is_numeric($yoast['seo_score'])) {
                $lqs = round(((float) $yoast['seo_score']) / 10, 1);
            }
            $rating = $this->firstNullableNumber($score->rating ?? null, $api['rating'] ?? null);
            $reviews = $this->firstNullableInt($score->reviews ?? null, $api['reviews'] ?? null);
            $listingUrl = ($shopifyRow && ! empty($shopifyRow->product_link))
                ? $shopifyRow->product_link
                : ($yoast['listing_url'] ?? $this->listingUrl($channel['listing_url'] ?? null, $listingId));

            $displaySku = trim((string) ($productMaster->sku ?? $normalizedSku));
            $parent = $productMaster ? (trim((string) ($productMaster->parent ?? '')) ?: null) : null;
            $latestAction = $latestActionsBySku->get($normalizeSku($displaySku));
            $hasAction = $latestAction !== null;

            $rows[] = [
                'sku' => $displaySku,
                'parent' => $parent,
                'is_parent' => false,
                'asin' => $listingId,
                'listing_id' => $listingId,
                'listing_url' => $listingUrl,
                'image' => $imageSrc,
                'inv' => $inv,
                'l30' => (int) $l30,
                'sessions' => (int) $sessions,
                'dil_percent' => $inv > 0 ? round(($l30 / $inv) * 100, 2) : 0,
                'price' => $price ? (float) $price : null,
                'rating' => $rating,
                'reviews' => $reviews,
                'cvr' => $sessions > 0 ? round(($l30 / $sessions) * 100, 2) : null,
                'lqs' => $lqs,
                'audit_findings' => $hasAuditColumns ? ($score->audit_findings ?? null) : null,
                'audit_suggestions' => $hasAuditColumns ? ($score->audit_suggestions ?? null) : null,
                'has_action' => $hasAction,
                'latest_action_text' => $hasAction ? $latestAction->action : null,
                'latest_action_user' => $hasAction ? ($latestAction->user->name ?? 'Unknown') : null,
                'latest_action_date' => $hasAction ? $latestAction->created_at->format('d M, h:i A') : null,
                'shopify_product_id' => $yoast['shopify_product_id'] ?? null,
                'focus_keyphrase' => $yoast['focus_keyphrase'] ?? null,
                'seo_title' => $yoast['seo_title'] ?? null,
                'seo_score' => $yoast['seo_score'] ?? null,
                'seo_rating' => $yoast['seo_rating'] ?? null,
                'readability_score' => $yoast['readability_score'] ?? null,
                'readability_rating' => $yoast['readability_rating'] ?? null,
                'seo_findings' => $yoast['findings'] ?? [],
            ];
        }

        $formatted = $this->withParentRows($rows);
        $this->saveSnapshot($slug, $formatted);

        return $formatted;
    }

    public function saveAction(string $slug, string $sku, string $action): LqsMarketplaceAction
    {
        $entry = LqsMarketplaceAction::create([
            'marketplace' => $slug,
            'sku' => trim($sku),
            'action' => trim($action),
            'user_id' => auth()->id(),
        ]);
        $entry->load('user:id,name');

        return $entry;
    }

    public function actionHistory(string $slug, string $sku)
    {
        return LqsMarketplaceAction::query()
            ->where('marketplace', $slug)
            ->where('sku', trim($sku))
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'action' => $entry->action,
                'user_name' => $entry->user->name ?? 'Unknown',
                'created_at' => $entry->created_at->format('d M, h:i A'),
            ]);
    }

    public function badgeHistory(string $slug, string $metric, int $days): array
    {
        $startDate = $days > 0 ? now()->subDays($days)->toDateString() : '2000-01-01';
        $today = now()->toDateString();

        $data = LqsMarketplaceHistory::query()
            ->where('marketplace', $slug)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $today)
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date->format('d M'),
                'value' => (float) ($row->{$metric} ?? 0),
            ]);

        if ($data->isEmpty()) {
            $data = collect([['date' => $today, 'value' => 0]]);
        }

        return $data->values()->all();
    }

    public function cvrHistory(string $slug, int $days): array
    {
        $startDate = $days > 0 ? now()->subDays($days)->toDateString() : '2000-01-01';
        $today = now()->toDateString();

        return LqsMarketplaceHistory::query()
            ->where('marketplace', $slug)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $today)
            ->orderBy('date')
            ->get()
            ->map(function ($row) {
                $cvr = ($row->total_sessions > 0)
                    ? round(($row->total_l30 / $row->total_sessions) * 100, 2)
                    : 0;

                return [
                    'date' => $row->date->format('d M'),
                    'value' => $cvr,
                ];
            })
            ->values()
            ->all();
    }

    public function importScores(string $slug, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku === '') {
                continue;
            }

            LqsMarketplaceScore::updateOrCreate(
                ['marketplace' => $slug, 'sku' => $sku],
                [
                    'lqs' => $this->nullableNumber($row['lqs'] ?? null),
                    'rating' => $this->nullableNumber($row['rating'] ?? null),
                    'reviews' => $this->nullableInt($row['reviews'] ?? null),
                    'l30' => $this->nullableInt($row['l30'] ?? null),
                    'sessions' => $this->nullableInt($row['sessions'] ?? null),
                    'price' => $this->nullableNumber($row['price'] ?? null),
                    'listing_id' => trim((string) ($row['listing_id'] ?? '')) ?: null,
                ]
            );
            $count++;
        }

        return $count;
    }

    public function exportRows(string $slug): array
    {
        $out = [];
        foreach ($this->tableRows($slug) as $row) {
            if (! empty($row['is_parent'])) {
                continue;
            }
            $out[] = [
                'sku' => $row['sku'],
                'listing_id' => $row['listing_id'] ?? '',
                'lqs' => $row['lqs'],
                'rating' => $row['rating'],
                'reviews' => $row['reviews'],
                'l30' => $row['l30'],
                'sessions' => $row['sessions'],
                'price' => $row['price'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{l30:?float,sessions:?float,price:?float,listing_id:?string,lqs:?float,rating:?float,reviews:?int}>
     */
    private function apiMetricsBySku(?string $source, callable $normalizeSku): array
    {
        if ($source === 'ebay' && class_exists(EbayMetric::class) && Schema::hasTable('ebay_metrics')) {
            return $this->mapEbayStyleMetrics(EbayMetric::query()->orderByDesc('id')->get(), $normalizeSku);
        }
        if ($source === 'ebay2' && class_exists(Ebay2Metric::class) && Schema::hasTable('ebay_2_metrics')) {
            return $this->mapEbayStyleMetrics(Ebay2Metric::query()->orderByDesc('id')->get(), $normalizeSku);
        }
        if ($source === 'ebay3' && class_exists(Ebay3Metric::class) && Schema::hasTable('ebay_3_metrics')) {
            return $this->mapEbayStyleMetrics(Ebay3Metric::query()->orderByDesc('id')->get(), $normalizeSku);
        }
        if ($source === 'temu' && class_exists(TemuMetric::class) && Schema::hasTable((new TemuMetric)->getTable())) {
            return $this->mapTemuStyleMetrics(TemuMetric::query()->orderByDesc('id')->get(), $normalizeSku);
        }
        if ($source === 'temu2' && class_exists(Temu2Metric::class) && Schema::hasTable('temu2_metrics')) {
            return $this->mapTemuStyleMetrics(Temu2Metric::query()->orderByDesc('id')->get(), $normalizeSku);
        }
        if ($source === 'shopify') {
            return $this->mapGenericMetrics(ShopifySku::all(), $normalizeSku, [
                'l30' => 'quantity',
                'price' => 'price',
                'listing_id' => 'sku',
            ]);
        }
        if ($source === 'walmart') {
            return $this->mapWalmartMetrics($normalizeSku);
        }
        if ($source === 'reverb' && class_exists(ReverbMetric::class) && Schema::hasTable('reverb_metric')) {
            return $this->mapGenericMetrics(ReverbMetric::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'l30',
                'price' => 'price',
                'listing_id' => 'product_id',
            ]);
        }
        if ($source === 'newegg' && class_exists(NeweggMetric::class) && Schema::hasTable('newegg_metric')) {
            return $this->mapGenericMetrics(NeweggMetric::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'l30',
                'price' => 'price',
                'listing_id' => 'product_id',
            ]);
        }
        if ($source === 'shein' && class_exists(SheinMetric::class) && Schema::hasTable('shein_metrics')) {
            return $this->mapGenericMetrics(SheinMetric::query()->orderByDesc('id')->get(), $normalizeSku, [
                'sessions' => 'views',
                'price' => 'price',
                'listing_id' => 'shein_sku_code',
                'rating' => 'rating',
                'reviews' => 'review_count',
            ]);
        }
        if ($source === 'aliexpress' && class_exists(AliexpressMetric::class) && Schema::hasTable('aliexpress_metric')) {
            return $this->mapGenericMetrics(AliexpressMetric::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'l30',
                'sessions' => 'views',
                'price' => 'price',
                'listing_id' => 'product_id',
                'rating' => 'avg_rating',
                'reviews' => 'reviews',
            ]);
        }
        if ($source === 'alibaba' && class_exists(AlibabaMetric::class) && Schema::hasTable('alibaba_metrics')) {
            return $this->mapGenericMetrics(AlibabaMetric::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'l30',
                'price' => 'price',
                'listing_id' => 'product_id',
            ]);
        }
        if ($source === 'faire' && class_exists(FaireMetric::class) && Schema::hasTable('faire_metric')) {
            return $this->mapGenericMetrics(FaireMetric::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'l30',
                'price' => 'price',
                'listing_id' => 'product_id',
            ]);
        }
        if ($source === 'doba' && class_exists(DobaMetric::class) && Schema::hasTable((new DobaMetric)->getTable())) {
            return $this->mapGenericMetrics(DobaMetric::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'quantity_l30',
                'sessions' => 'impressions',
                'price' => 'self_pick_price',
                'listing_id' => 'item_id',
            ]);
        }

        return [];
    }

    private function mapEbayStyleMetrics($rows, callable $normalizeSku): array
    {
        $map = [];
        foreach ($rows as $row) {
            $key = $normalizeSku($row->sku);
            if ($key === '' || isset($map[$key])) {
                continue;
            }
            $map[$key] = [
                'l30' => $row->ebay_l30 ?? null,
                'sessions' => $row->views ?? null,
                'price' => $row->ebay_price ?? null,
                'listing_id' => $row->item_id ?? null,
                'lqs' => null,
                'rating' => null,
                'reviews' => null,
            ];
        }

        return $map;
    }

    private function mapTemuStyleMetrics($rows, callable $normalizeSku): array
    {
        $map = [];
        foreach ($rows as $row) {
            $key = $normalizeSku($row->sku);
            if ($key === '' || isset($map[$key])) {
                continue;
            }
            $map[$key] = [
                'l30' => $row->quantity_purchased_l30 ?? null,
                'sessions' => $row->product_impressions_l30 ?? $row->product_clicks_l30 ?? null,
                'price' => $row->base_price ?? $row->temu_sheet_price ?? null,
                'listing_id' => $row->goods_id ?? $row->sku_id ?? null,
                'lqs' => null,
                'rating' => null,
                'reviews' => null,
            ];
        }

        return $map;
    }

    private function mapGenericMetrics($rows, callable $normalizeSku, array $fields): array
    {
        $map = [];
        foreach ($rows as $row) {
            $key = $normalizeSku($row->sku ?? '');
            if ($key === '' || isset($map[$key])) {
                continue;
            }
            $map[$key] = [
                'l30' => isset($fields['l30']) ? ($row->{$fields['l30']} ?? null) : null,
                'sessions' => isset($fields['sessions']) ? ($row->{$fields['sessions']} ?? null) : null,
                'price' => isset($fields['price']) ? ($row->{$fields['price']} ?? null) : null,
                'listing_id' => isset($fields['listing_id']) ? ($row->{$fields['listing_id']} ?? null) : null,
                'lqs' => isset($fields['lqs']) ? ($row->{$fields['lqs']} ?? null) : null,
                'rating' => isset($fields['rating']) ? ($row->{$fields['rating']} ?? null) : null,
                'reviews' => isset($fields['reviews']) ? ($row->{$fields['reviews']} ?? null) : null,
            ];
        }

        return $map;
    }

    private function mapWalmartMetrics(callable $normalizeSku): array
    {
        $map = [];
        if (class_exists(WalmartMetrics::class) && Schema::hasTable('walmart_metrics')) {
            $map = $this->mapGenericMetrics(WalmartMetrics::query()->orderByDesc('id')->get(), $normalizeSku, [
                'l30' => 'l30',
                'price' => 'price',
                'listing_id' => 'sku',
            ]);
        }

        if (class_exists(WalmartListingViewsData::class) && Schema::hasTable('walmart_listing_views_data')) {
            foreach (WalmartListingViewsData::query()->orderByDesc('id')->get() as $row) {
                $key = $normalizeSku($row->sku);
                if ($key === '') {
                    continue;
                }
                $existing = $map[$key] ?? [
                    'l30' => null,
                    'sessions' => null,
                    'price' => null,
                    'listing_id' => null,
                    'lqs' => null,
                    'rating' => null,
                    'reviews' => null,
                ];
                $existing['sessions'] = $existing['sessions'] ?? $row->page_views;
                $existing['price'] = $existing['price'] ?? $row->walmart_price;
                $existing['listing_id'] = $existing['listing_id'] ?? $row->item_id;
                $existing['lqs'] = $existing['lqs'] ?? $this->nullableNumber($row->listing_quality ?? null);
                $existing['rating'] = $existing['rating'] ?? $this->nullableNumber($row->ratings ?? null);
                $map[$key] = $existing;
            }
        }

        return $map;
    }

    private function withParentRows(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['parent'] ?: $row['sku']][] = $row;
        }

        $formatted = [];
        foreach ($groups as $parentKey => $childRows) {
            $totalInv = array_sum(array_column($childRows, 'inv'));
            $totalL30 = array_sum(array_column($childRows, 'l30'));
            $totalSess = array_sum(array_column($childRows, 'sessions'));
            $formatted[] = [
                'sku' => $parentKey,
                'parent' => '',
                'asin' => null,
                'listing_id' => null,
                'listing_url' => null,
                'image' => '',
                'inv' => $totalInv,
                'l30' => $totalL30,
                'sessions' => $totalSess,
                'dil_percent' => $totalInv > 0 ? round(($totalL30 / $totalInv) * 100, 2) : 0,
                'price' => null,
                'rating' => null,
                'reviews' => null,
                'cvr' => $totalSess > 0 ? round(($totalL30 / $totalSess) * 100, 2) : null,
                'lqs' => null,
                'audit_findings' => null,
                'audit_suggestions' => null,
                'has_action' => false,
                'latest_action_text' => null,
                'latest_action_user' => null,
                'latest_action_date' => null,
                'is_parent' => true,
                'shopify_product_id' => null,
                'focus_keyphrase' => null,
                'seo_title' => null,
                'seo_score' => null,
                'seo_rating' => null,
                'readability_score' => null,
                'readability_rating' => null,
                'seo_findings' => [],
            ];
            foreach ($childRows as $child) {
                $formatted[] = $child;
            }
        }

        return $formatted;
    }

    private function saveSnapshot(string $slug, array $rows): void
    {
        if (! Schema::hasTable('lqs_marketplace_history')) {
            return;
        }

        $childRows = collect($rows)->filter(fn ($row) => ! ($row['is_parent'] ?? false));
        $totalInv = 0;
        $totalL30 = 0;
        $totalSess = 0;
        $dilSum = 0;
        $dilCount = 0;
        $lqsSum = 0;
        $lqsCount = 0;
        $ratingSum = 0;
        $ratingCount = 0;
        $lqsBelow9 = 0;

        foreach ($childRows as $row) {
            $inv = (float) ($row['inv'] ?? 0);
            $l30 = (float) ($row['l30'] ?? 0);
            $sess = (float) ($row['sessions'] ?? 0);
            $lqs = (float) ($row['lqs'] ?? 0);
            $rating = (float) ($row['rating'] ?? 0);
            $totalInv += $inv;
            $totalL30 += $l30;
            $totalSess += $sess;
            if ($inv > 0) {
                $dilSum += ($l30 / $inv) * 100;
                $dilCount++;
            }
            if ($lqs > 0) {
                $lqsSum += $lqs;
                $lqsCount++;
            }
            if ($rating > 0) {
                $ratingSum += $rating;
                $ratingCount++;
            }
            if ($lqs > 0 && $lqs < ($slug === 'ebay' ? 90 : 9)) {
                $lqsBelow9++;
            }
        }

        LqsMarketplaceHistory::updateOrCreate(
            ['marketplace' => $slug, 'date' => now()->toDateString()],
            [
                'total_inv' => round($totalInv, 2),
                'total_l30' => round($totalL30, 2),
                'total_sessions' => round($totalSess, 2),
                'avg_dil' => $dilCount > 0 ? round($dilSum / $dilCount, 2) : 0,
                'avg_lqs' => $lqsCount > 0 ? round($lqsSum / $lqsCount, 2) : 0,
                'avg_rating' => $ratingCount > 0 ? round($ratingSum / $ratingCount, 2) : 0,
                'lqs_below_9_count' => $lqsBelow9,
            ]
        );
    }

    private function firstNumber(...$values)
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '' && is_numeric($value)) {
                return $value;
            }
        }

        return 0;
    }

    private function nullableNumber($value)
    {
        return ($value === null || $value === '' || ! is_numeric($value)) ? null : (float) $value;
    }

    private function nullableInt($value)
    {
        return ($value === null || $value === '' || ! is_numeric($value)) ? null : (int) $value;
    }

    private function firstNullableNumber(...$values)
    {
        foreach ($values as $value) {
            $number = $this->nullableNumber($value);
            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    private function firstNullableInt(...$values)
    {
        foreach ($values as $value) {
            $number = $this->nullableInt($value);
            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function shopifySeoScoresBySku(): array
    {
        try {
            return app(LqsShopifySeoService::class)->scoresBySku();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function listingUrl(?string $template, $listingId): ?string
    {
        if (! $template || $listingId === null || $listingId === '') {
            return null;
        }

        return str_replace('{id}', rawurlencode((string) $listingId), $template);
    }
}
