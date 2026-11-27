<?php

namespace App\Http\Controllers\Reviews;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\MarketplaceReview;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\WalmartPriceData;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use App\Services\Reviews\JudgeMeReviewPusher;
use App\Services\Reviews\MarketplaceReviewFetcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * "Reviews" tab: reviews of every product from every marketplace (not Shopify), and the push to Shopify.
 */
class MarketplaceReviewsController extends Controller
{
    /** Marketplaces that are Shopify stores themselves, so they are not a review source. */
    protected const SHOPIFY_SLUGS = ['shopify', 'pls', 'b5cb2b'];

    public function index()
    {
        $pusher = app(JudgeMeReviewPusher::class);

        return view('reviews.marketplace-reviews', [
            'marketplaces' => $this->marketplaces(),
            'pushConfigured' => $pusher->isConfigured(),
            'pushHint' => $pusher->configurationHint(),
            'tableReady' => Schema::hasTable('marketplace_reviews'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (! Schema::hasTable('marketplace_reviews')) {
            return response()->json(['rows' => [], 'stats' => $this->emptyStats()]);
        }

        $marketplace = strtolower(trim((string) $request->query('marketplace', '')));
        $query = MarketplaceReview::query();
        if ($marketplace !== '' && $marketplace !== 'all') {
            $query->where('marketplace', $marketplace);
        }

        // Stats follow the marketplace choice but ignore the other filters, so the chips stay stable.
        $statsRows = (clone $query)
            ->selectRaw('marketplace, COUNT(*) as c, AVG(rating) as a, SUM(shopify_status = ?) as pushed, SUM(shopify_status = ?) as pending, SUM(shopify_status = ?) as failed', [
                MarketplaceReview::SHOPIFY_PUSHED, MarketplaceReview::SHOPIFY_PENDING, MarketplaceReview::SHOPIFY_FAILED,
            ])
            ->groupBy('marketplace')
            ->get();

        $rating = (int) $request->query('rating', 0);
        if ($rating >= 1 && $rating <= 5) {
            $query->where('rating', $rating);
        }
        $shopify = trim((string) $request->query('shopify', ''));
        if (in_array($shopify, ['pending', 'pushed', 'failed', 'skipped'], true)) {
            $query->where('shopify_status', $shopify);
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('sku', 'like', $like)->orWhere('body', 'like', $like)
                    ->orWhere('title', 'like', $like)->orWhere('reviewer_name', 'like', $like);
            });
        }

        $reviews = $query->orderByDesc('reviewed_at')->orderByDesc('id')->limit(6000)->get();

        $skus = $reviews->pluck('sku')->unique()->values()->all();
        $images = [];
        $titles = [];
        foreach (array_chunk($skus, 500) as $chunk) {
            ProductMaster::query()->whereIn('sku', $chunk)->get(['sku', 'main_image'])
                ->each(function ($p) use (&$images) {
                    $images[(string) $p->sku] = (string) ($p->main_image ?? '');
                });
        }
        try {
            foreach (ShopifySku::mapByProductSkus($skus) as $sku => $row) {
                $titles[(string) $sku] = (string) ($row->product_title ?? '');
                if (($images[(string) $sku] ?? '') === '' && ! empty($row->image_src)) {
                    $images[(string) $sku] = (string) $row->image_src;
                }
            }
        } catch (\Throwable) {
            // titles are optional
        }

        $labels = $this->labelMap();
        $rows = $reviews->map(function (MarketplaceReview $r) use ($images, $titles, $labels) {
            return [
                'id' => $r->id,
                'sku' => $r->sku,
                'title_product' => $titles[$r->sku] ?? '',
                'image' => $images[$r->sku] ?? '',
                'marketplace' => $r->marketplace,
                'marketplace_label' => $labels[$r->marketplace] ?? strtoupper($r->marketplace),
                'rating' => $r->rating,
                'title' => $r->title,
                'body' => $r->body,
                'reviewer' => $r->reviewer_name,
                'verified' => (bool) $r->verified,
                'reviewed_at' => $r->reviewed_at ? $r->reviewed_at->format('Y-m-d') : null,
                'review_url' => $r->review_url,
                'source' => $r->source,
                'shopify_status' => $r->shopify_status,
                'shopify_error' => $r->shopify_error,
                'shopify_pushed_at' => $r->shopify_pushed_at ? $r->shopify_pushed_at->format('Y-m-d H:i') : null,
            ];
        })->values();

        $perMarketplace = [];
        $total = 0;
        $sum = 0.0;
        $pushed = $pending = $failed = 0;
        foreach ($statsRows as $s) {
            $perMarketplace[$s->marketplace] = ['count' => (int) $s->c, 'avg' => $s->a !== null ? round((float) $s->a, 2) : null];
            $total += (int) $s->c;
            $sum += (float) $s->a * (int) $s->c;
            $pushed += (int) $s->pushed;
            $pending += (int) $s->pending;
            $failed += (int) $s->failed;
        }

        return response()->json([
            'rows' => $rows,
            'stats' => [
                'total' => $total,
                'avg' => $total > 0 ? round($sum / $total, 2) : null,
                'pushed' => $pushed,
                'pending' => $pending,
                'failed' => $failed,
                'per_marketplace' => $perMarketplace,
                'products' => count($skus),
                'truncated' => $total > 6000,
            ],
        ]);
    }

    /**
     * Fetch one batch of SKUs for a marketplace. The page calls this again with next_offset until done.
     */
    public function fetch(Request $request, MarketplaceReviewFetcher $fetcher): JsonResponse
    {
        @set_time_limit(120);
        $data = $request->validate([
            'marketplace' => 'required|string|max:40',
            'offset' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|max:191',
        ]);
        $marketplace = strtolower(trim($data['marketplace']));
        if (! MarketplaceReviewFetcher::canAutoFetch($marketplace)) {
            return response()->json([
                'success' => false,
                'message' => 'There is no automatic review source for this marketplace. Use Import CSV.',
            ], 422);
        }

        $skus = $this->fetchableSkus($marketplace, trim((string) ($data['sku'] ?? '')));
        $offset = (int) ($data['offset'] ?? 0);
        $batch = array_slice($skus, $offset, 5);

        $fetched = 0;
        $new = 0;
        $errors = [];
        foreach ($batch as $sku) {
            $res = $fetcher->fetch($marketplace, $sku);
            $fetched += $res['fetched'];
            $new += $res['new'];
            if ($res['error'] !== null) {
                $errors[] = $sku.': '.$res['error'];
            }
        }

        $next = $offset + count($batch);

        return response()->json([
            'success' => true,
            'total_skus' => count($skus),
            'processed' => $next,
            'next_offset' => $next < count($skus) ? $next : null,
            'fetched' => $fetched,
            'new' => $new,
            'errors' => array_slice($errors, 0, 10),
        ]);
    }

    /**
     * Push one batch of pending reviews to Shopify (Judge.me). The page repeats until remaining is 0.
     */
    public function push(Request $request, JudgeMeReviewPusher $pusher): JsonResponse
    {
        @set_time_limit(120);
        $data = $request->validate([
            'ids' => 'nullable|array|max:200',
            'ids.*' => 'integer',
            'marketplace' => 'nullable|string|max:40',
            'include_failed' => 'nullable|boolean',
        ]);

        if (! $pusher->isConfigured()) {
            return response()->json(['success' => false, 'message' => $pusher->configurationHint()], 422);
        }

        $statuses = [MarketplaceReview::SHOPIFY_PENDING];
        if (! empty($data['include_failed'])) {
            $statuses[] = MarketplaceReview::SHOPIFY_FAILED;
        }

        $base = MarketplaceReview::query()->whereIn('shopify_status', $statuses)->whereNotNull('rating');
        if (! empty($data['ids'])) {
            $base->whereIn('id', $data['ids']);
        } elseif (! empty($data['marketplace']) && strtolower($data['marketplace']) !== 'all') {
            $base->where('marketplace', strtolower($data['marketplace']));
        }

        // Failed rows tried in this run must not come back, so the loop always ends.
        $afterId = (int) $request->input('after_id', 0);
        $batch = (clone $base)->where('id', '>', $afterId)->orderBy('id')->limit(15)->get();

        $pushed = 0;
        $failed = 0;
        $errors = [];
        $lastId = $afterId;
        foreach ($batch as $review) {
            $lastId = $review->id;
            $res = $pusher->push($review);
            if ($res['success']) {
                $pushed++;
            } else {
                $failed++;
                $errors[] = $review->sku.': '.$res['message'];
            }
        }

        $remaining = (clone $base)->where('id', '>', $lastId)->count();

        return response()->json([
            'success' => true,
            'pushed' => $pushed,
            'failed' => $failed,
            'errors' => array_slice(array_values(array_unique($errors)), 0, 6),
            'remaining' => $remaining,
            'after_id' => $remaining > 0 ? $lastId : null,
        ]);
    }

    public function import(Request $request, MarketplaceReviewFetcher $fetcher): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480',
            'marketplace' => 'nullable|string|max:40',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (! $handle) {
            return response()->json(['success' => false, 'message' => 'Could not read the file'], 422);
        }
        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);

            return response()->json(['success' => false, 'message' => 'The file is empty'], 422);
        }
        $header = array_map(static fn ($h) => strtolower(trim(str_replace(["\xEF\xBB\xBF", ' '], ['', '_'], (string) $h))), $header);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || count(array_filter($line, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $assoc = [];
            foreach ($header as $i => $name) {
                $assoc[$name] = $line[$i] ?? '';
            }
            $rows[] = $assoc;
        }
        fclose($handle);

        $stats = $fetcher->importRows($rows, $request->input('marketplace'));

        return response()->json(['success' => true] + $stats);
    }

    public function store(Request $request, MarketplaceReviewFetcher $fetcher): JsonResponse
    {
        $data = $request->validate([
            'sku' => 'required|string|max:191',
            'marketplace' => 'required|string|max:40',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:5000',
            'reviewer' => 'nullable|string|max:191',
            'date' => 'nullable|string|max:40',
        ]);

        $isNew = $fetcher->store($data['marketplace'], $data['sku'], [
            'rating' => $data['rating'],
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'reviewer' => $data['reviewer'] ?? '',
            'date' => $data['date'] ?? null,
        ], 'manual');

        return response()->json(['success' => true, 'new' => $isNew]);
    }

    public function destroy(int $id): JsonResponse
    {
        MarketplaceReview::query()->whereKey($id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return list<array{slug: string, label: string, auto: bool}>
     */
    protected function marketplaces(): array
    {
        $out = [];
        foreach (MarketplaceManagerRegistry::channels() as $channel) {
            $slug = strtolower((string) ($channel['slug'] ?? ''));
            if ($slug === '' || in_array($slug, self::SHOPIFY_SLUGS, true) || ! ($channel['enabled'] ?? false)) {
                continue;
            }
            $out[$slug] = ['slug' => $slug, 'label' => (string) ($channel['label'] ?? $slug), 'auto' => MarketplaceReviewFetcher::canAutoFetch($slug)];
        }
        if (! isset($out['walmart'])) {
            $out['walmart'] = ['slug' => 'walmart', 'label' => 'Walmart', 'auto' => true];
        }

        return array_values($out);
    }

    /**
     * @return array<string, string>
     */
    protected function labelMap(): array
    {
        $map = [];
        foreach ($this->marketplaces() as $m) {
            $map[$m['slug']] = $m['label'];
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    protected function fetchableSkus(string $marketplace, string $onlySku): array
    {
        if ($marketplace === 'walmart') {
            $q = WalmartPriceData::query()->whereNotNull('item_id')->where('item_id', '!=', '');
        } else {
            $q = AmazonDatasheet::query()->whereNotNull('asin')->where('asin', '!=', '');
        }
        $q->whereNotNull('sku')->where('sku', '!=', '');
        if ($onlySku !== '') {
            $q->where('sku', $onlySku);
        }

        return $q->orderBy('sku')->distinct()->pluck('sku')
            ->map(static fn ($s) => trim((string) $s))->filter()->unique()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyStats(): array
    {
        return ['total' => 0, 'avg' => null, 'pushed' => 0, 'pending' => 0, 'failed' => 0, 'per_marketplace' => [], 'products' => 0, 'truncated' => false];
    }
}
