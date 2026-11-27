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

        // eBay / Reverb: the whole seller feedback list, a couple of pages per call.
        if (MarketplaceReviewFetcher::isAccountWide($marketplace)) {
            if (! Schema::hasTable('marketplace_reviews')) {
                return response()->json(['success' => false, 'message' => 'marketplace_reviews table is missing, run php artisan migrate'], 422);
            }
            $page = max(1, (int) ($data['offset'] ?? 0) + 1);
            $res = $fetcher->fetchAccountPages($marketplace, $page, 2);

            return response()->json([
                'success' => true,
                'unit' => 'pages',
                'total_skus' => $res['total_pages'],
                'processed' => $res['next_page'] === null ? $res['total_pages'] : $res['next_page'] - 1,
                'next_offset' => $res['next_page'] === null ? null : $res['next_page'] - 1,
                'fetched' => $res['fetched'],
                'new' => $res['new'],
                'errors' => $res['error'] ? [$res['error']] : [],
            ]);
        }

        $skus = $fetcher->fetchableSkus($marketplace, trim((string) ($data['sku'] ?? '')));
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

    /**
     * Download reviews as a Judge.me CSV (same columns as a Judge.me export), to upload in
     * Judge.me → Reviews → Import. Works without an API token.
     */
    public function export(Request $request, JudgeMeReviewPusher $pusher)
    {
        @set_time_limit(300);
        $marketplace = strtolower(trim((string) $request->query('marketplace', '')));
        $shopify = trim((string) $request->query('shopify', 'pending'));
        $mark = $request->boolean('mark');

        $query = MarketplaceReview::query()->whereNotNull('rating');
        if ($marketplace !== '' && $marketplace !== 'all') {
            $query->where('marketplace', $marketplace);
        }
        if (in_array($shopify, ['pending', 'pushed', 'failed', 'skipped'], true)) {
            $query->where('shopify_status', $shopify);
        }
        $ids = array_filter(array_map('intval', (array) $request->query('ids', [])));
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }
        // Positive only = 4 and 5 stars.
        $minRating = (int) $request->query('min_rating', 0);
        if ($minRating >= 1 && $minRating <= 5) {
            $query->where('rating', '>=', $minRating);
        }

        $headers = ['title', 'body', 'rating', 'review_date', 'source', 'curated', 'reviewer_name', 'reviewer_email', 'product_id',
            'product_handle', 'reply', 'reply_date', 'picture_urls', 'ip_address', 'location', 'metaobject_handle', 'status'];

        $name = 'reviews-judgeme-'.($minRating >= 4 ? 'positive-' : '').($marketplace !== '' ? $marketplace : 'all').'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query, $pusher, $headers, $mark) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            $handles = [];
            $query->orderBy('id')->chunkById(500, function ($chunk) use ($out, $pusher, &$handles, $mark) {
                $need = $chunk->pluck('sku')->unique()->diff(array_keys($handles))->values()->all();
                if ($need !== []) {
                    foreach (ShopifySku::mapByProductSkus($need) as $sku => $row) {
                        $handles[(string) $sku] = preg_match('#/products/([^/?\#]+)#', (string) ($row->product_link ?? ''), $m) ? $m[1] : '';
                    }
                    foreach ($need as $sku) {
                        $handles[(string) $sku] ??= '';
                    }
                }
                $done = [];
                foreach ($chunk as $r) {
                    $handle = $handles[$r->sku] ?? '';
                    if ($handle === '') {
                        continue; // not a Shopify product, Judge.me could not attach it
                    }
                    fputcsv($out, [
                        (string) ($r->title ?? ''),
                        (string) ($r->body ?? ''),
                        (int) $r->rating,
                        ($r->reviewed_at ?? $r->created_at)->utc()->format('Y-m-d H:i:s').' UTC',
                        'web',
                        'ok',
                        trim((string) $r->reviewer_name) !== '' ? $r->reviewer_name : 'Verified Buyer',
                        $pusher->emailFor($r),
                        '',
                        $handle,
                        '', '',
                        is_array($r->images) ? implode(',', $r->images) : '',
                        '', '', '',
                        'Published',
                    ]);
                    $done[] = $r->id;
                }
                if ($mark && $done !== []) {
                    MarketplaceReview::query()->whereIn('id', $done)->update([
                        'shopify_status' => MarketplaceReview::SHOPIFY_PUSHED,
                        'shopify_pushed_at' => now(),
                        'shopify_error' => 'Exported in a Judge.me CSV',
                    ]);
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
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

        // Judge.me exports can hold tens of thousands of rows: read and save them in chunks.
        @set_time_limit(600);
        $stats = ['saved' => 0, 'skipped' => 0, 'errors' => 0];
        $rows = [];
        $flush = function () use (&$rows, &$stats, $fetcher, $request): void {
            if ($rows === []) {
                return;
            }
            $part = $fetcher->importRows($rows, $request->input('marketplace'));
            foreach ($part as $k => $v) {
                $stats[$k] += $v;
            }
            $rows = [];
        };
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || count(array_filter($line, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $assoc = [];
            foreach ($header as $i => $name) {
                $assoc[$name] = $line[$i] ?? '';
            }
            $rows[] = $assoc;
            if (count($rows) >= 500) {
                $flush();
            }
        }
        fclose($handle);
        $flush();

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
        // Reviews that came in through a Judge.me CSV (their marketplace is not known).
        $out['judgeme'] = ['slug' => 'judgeme', 'label' => 'Judge.me import', 'auto' => false];

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
     * @return array<string, mixed>
     */
    protected function emptyStats(): array
    {
        return ['total' => 0, 'avg' => null, 'pushed' => 0, 'pending' => 0, 'failed' => 0, 'per_marketplace' => [], 'products' => 0, 'truncated' => false];
    }
}
