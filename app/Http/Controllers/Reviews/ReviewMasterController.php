<?php

namespace App\Http\Controllers\Reviews;

use App\Http\Controllers\Controller;
use App\Jobs\FetchReviewsJob;
use App\Models\ProductMaster;
use App\Models\ReviewAlert;
use App\Models\ReviewIssuesSummary;
use App\Models\SkuReview;
use App\Models\Supplier;
use App\Services\ReviewAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ReviewMasterController extends Controller
{
    // -------------------------------------------------------------------------
    // Main View
    // -------------------------------------------------------------------------

    public function index(): \Illuminate\View\View
    {
        $suppliers   = Supplier::orderBy('name')->get(['id', 'name']);
        $openAlerts  = ReviewAlert::open()->count();
        $dashStats   = $this->getDashboardStats();

        return view('reviews.review-master', compact('suppliers', 'openAlerts', 'dashStats'));
    }

    // -------------------------------------------------------------------------
    // Server-Side DataTable JSON
    // -------------------------------------------------------------------------

    /**
     * Return all reviews as a flat JSON array. Tabulator handles client-side
     * paging, sorting, filtering and search, so we just stream the rows.
     * Optional query params (?sku=, ?marketplace=, ?date_from=, ?date_to=, …)
     * are supported so the URL can be deep-linked.
     */
    public function getData(Request $request): JsonResponse
    {
        $query = SkuReview::query()
            ->leftJoin('suppliers', 'sku_reviews.supplier_id', '=', 'suppliers.id')
            ->leftJoin('product_master', 'sku_reviews.product_id', '=', 'product_master.id')
            ->select([
                'sku_reviews.id',
                'sku_reviews.sku',
                'product_master.title150 as product_name',
                'sku_reviews.marketplace',
                'sku_reviews.rating',
                'sku_reviews.review_title',
                'sku_reviews.review_text',
                'sku_reviews.reviewer_name',
                'sku_reviews.review_date',
                'sku_reviews.sentiment',
                'sku_reviews.issue_category',
                'sku_reviews.ai_summary',
                'sku_reviews.ai_reply',
                'sku_reviews.department',
                'sku_reviews.source_type',
                'sku_reviews.is_flagged',
                'suppliers.name as supplier_name',
            ]);

        if ($sku = $request->input('sku')) {
            $query->where('sku_reviews.sku', 'like', "%{$sku}%");
        }
        if ($supplier = $request->input('supplier_id')) {
            $query->where('sku_reviews.supplier_id', $supplier);
        }
        if ($marketplace = $request->input('marketplace')) {
            $query->where('sku_reviews.marketplace', $marketplace);
        }
        if ($rating = $request->input('rating')) {
            $query->where('sku_reviews.rating', $rating);
        }
        if ($issue = $request->input('issue_category')) {
            $query->where('sku_reviews.issue_category', $issue);
        }
        if ($sentiment = $request->input('sentiment')) {
            $query->where('sku_reviews.sentiment', $sentiment);
        }
        if ($from = $request->input('date_from')) {
            $query->where('sku_reviews.review_date', '>=', $from);
        }
        if ($to = $request->input('date_to')) {
            $query->where('sku_reviews.review_date', '<=', $to);
        }

        $rows = $query->orderByDesc('sku_reviews.review_date')
            ->orderByDesc('sku_reviews.id')
            ->get()
            ->map(function ($r) {
                return [
                    'id'             => (int) $r->id,
                    'sku'            => $r->sku,
                    'product_name'   => $r->product_name,
                    'marketplace'    => $r->marketplace,
                    'rating'         => $r->rating !== null ? (int) $r->rating : null,
                    'review_title'   => $r->review_title,
                    'review_text'    => $r->review_text,
                    'reviewer_name'  => $r->reviewer_name,
                    'review_date'    => optional($r->review_date)->format('Y-m-d'),
                    'sentiment'      => $r->sentiment,
                    'issue_category' => $r->issue_category,
                    'ai_summary'     => $r->ai_summary,
                    'ai_reply'       => $r->ai_reply,
                    'department'     => $r->department,
                    'source_type'    => $r->source_type,
                    'is_flagged'     => (bool) $r->is_flagged,
                    'supplier_name'  => $r->supplier_name,
                ];
            });

        return response()->json($rows);
    }

    // -------------------------------------------------------------------------
    // Column visibility (per-user preferences stored on disk)
    // -------------------------------------------------------------------------

    public function getColumnVisibility(): JsonResponse
    {
        $defaults = [
            'sku'            => true,
            'product_name'   => true,
            'marketplace'    => true,
            'rating'         => true,
            'review_title'   => true,
            'review_text'    => true,
            'reviewer_name'  => false,
            'review_date'    => true,
            'sentiment'      => true,
            'issue_category' => true,
            'supplier_name'  => true,
            'department'     => false,
            'source_type'    => false,
            'is_flagged'     => true,
            'actions'        => true,
        ];

        try {
            $path = storage_path('app/reviews_column_visibility.json');
            if (is_file($path)) {
                $saved = json_decode((string) file_get_contents($path), true);
                if (is_array($saved)) {
                    return response()->json(array_merge($defaults, $saved));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Reviews column visibility read failed: ' . $e->getMessage());
        }

        return response()->json($defaults);
    }

    public function saveColumnVisibility(Request $request): JsonResponse
    {
        try {
            $visibility = $request->input('visibility', []);
            if (!is_array($visibility)) {
                return response()->json(['success' => false, 'message' => 'Invalid payload.'], 422);
            }
            $path = storage_path('app/reviews_column_visibility.json');
            file_put_contents($path, json_encode($visibility, JSON_PRETTY_PRINT));
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('Reviews column visibility save failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to save preferences.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Dashboard Stats (cached)
    // -------------------------------------------------------------------------

    public function getDashboardStats(): array
    {
        return Cache::remember('review_dashboard_stats', 7200, function () {
            $total    = SkuReview::count();
            $negative = SkuReview::where('sentiment', 'negative')->count();
            $negPct   = $total > 0 ? round(($negative / $total) * 100, 1) : 0;

            // Top issue
            $topIssueRow = SkuReview::whereNotNull('issue_category')
                ->where('issue_category', '!=', 'other')
                ->select('issue_category', DB::raw('count(*) as cnt'))
                ->groupBy('issue_category')
                ->orderByDesc('cnt')
                ->first();

            // Worst SKU by negative rate
            $worstSku = ReviewIssuesSummary::orderByDesc('negative_rate')
                ->where('total_reviews', '>=', 5)
                ->first();

            // Worst supplier
            $worstSupplier = DB::table('review_issues_summary')
                ->join('suppliers', 'review_issues_summary.supplier_id', '=', 'suppliers.id')
                ->select('suppliers.name', DB::raw('SUM(negative_reviews) as neg'), DB::raw('SUM(total_reviews) as tot'))
                ->groupBy('suppliers.id', 'suppliers.name')
                ->having('tot', '>=', 5)
                ->orderByDesc('neg')
                ->first();

            $openAlerts = ReviewAlert::open()->count();

            return [
                'total_reviews'    => $total,
                'negative_pct'     => $negPct,
                'top_issue'        => $topIssueRow?->issue_category ?? 'N/A',
                'top_issue_count'  => $topIssueRow?->cnt ?? 0,
                'worst_sku'        => $worstSku?->sku ?? 'N/A',
                'worst_sku_rate'   => $worstSku?->negative_rate ?? 0,
                'worst_supplier'   => $worstSupplier?->name ?? 'N/A',
                'open_alerts'      => $openAlerts,
            ];
        });
    }

    // -------------------------------------------------------------------------
    // SKU Detail (modal data)
    // -------------------------------------------------------------------------

    public function skuDetail(string $sku): JsonResponse
    {
        $reviews = SkuReview::where('sku', $sku)
            ->orderByDesc('review_date')
            ->get(['id', 'rating', 'sentiment', 'issue_category', 'review_date', 'review_title', 'ai_summary']);

        $summary = ReviewIssuesSummary::where('sku', $sku)->first();

        // Rating trend (last 12 weeks)
        $trend = SkuReview::where('sku', $sku)
            ->whereNotNull('review_date')
            ->where('review_date', '>=', Carbon::now()->subWeeks(12))
            ->select(DB::raw('YEARWEEK(review_date) as week'), DB::raw('AVG(rating) as avg_rating'), DB::raw('COUNT(*) as count'))
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        // Issue distribution
        $issueDistribution = SkuReview::where('sku', $sku)
            ->whereNotNull('issue_category')
            ->select('issue_category', DB::raw('count(*) as cnt'))
            ->groupBy('issue_category')
            ->orderByDesc('cnt')
            ->get();

        // Top complaints
        $topComplaints = SkuReview::where('sku', $sku)
            ->where('sentiment', 'negative')
            ->whereNotNull('ai_summary')
            ->orderByDesc('id')
            ->limit(10)
            ->pluck('ai_summary');

        return response()->json([
            'sku'               => $sku,
            'summary'           => $summary,
            'reviews'           => $reviews,
            'trend'             => $trend,
            'issue_distribution' => $issueDistribution,
            'top_complaints'    => $topComplaints,
        ]);
    }

    // -------------------------------------------------------------------------
    // Supplier Intelligence
    // -------------------------------------------------------------------------

    public function supplierIntelligence(): JsonResponse
    {
        $data = Cache::remember('review_supplier_intel', 3600, function () {
            return DB::table('review_issues_summary')
                ->leftJoin('suppliers', 'review_issues_summary.supplier_id', '=', 'suppliers.id')
                ->select([
                    'suppliers.id as supplier_id',
                    'suppliers.name as supplier_name',
                    DB::raw('SUM(total_reviews) as total'),
                    DB::raw('SUM(negative_reviews) as negative'),
                    DB::raw('SUM(issue_quality) as quality'),
                    DB::raw('SUM(issue_packaging) as packaging'),
                    DB::raw('SUM(issue_shipping) as shipping'),
                    DB::raw('SUM(issue_service) as service'),
                    DB::raw('COUNT(DISTINCT review_issues_summary.sku) as sku_count'),
                    DB::raw('ROUND(SUM(negative_reviews)/NULLIF(SUM(total_reviews),0)*100,1) as neg_rate'),
                ])
                ->groupBy('suppliers.id', 'suppliers.name')
                ->orderByDesc('negative')
                ->get();
        });

        return response()->json($data);
    }

    // -------------------------------------------------------------------------
    // AI Insights Panel
    // -------------------------------------------------------------------------

    public function aiInsights(): JsonResponse
    {
        $data = Cache::remember('review_ai_insights', 3600, function () {
            $weekAgo = Carbon::now()->subWeek()->toDateString();

            // Top 5 problems this week
            $topProblems = SkuReview::where('review_date', '>=', $weekAgo)
                ->whereNotNull('issue_category')
                ->where('issue_category', '!=', 'other')
                ->select('issue_category', DB::raw('count(*) as cnt'))
                ->groupBy('issue_category')
                ->orderByDesc('cnt')
                ->limit(5)
                ->get();

            // Most complained supplier
            $mostComplainedSupplier = DB::table('sku_reviews')
                ->join('suppliers', 'sku_reviews.supplier_id', '=', 'suppliers.id')
                ->where('sku_reviews.sentiment', 'negative')
                ->where('sku_reviews.review_date', '>=', $weekAgo)
                ->select('suppliers.name', DB::raw('count(*) as cnt'))
                ->groupBy('suppliers.id', 'suppliers.name')
                ->orderByDesc('cnt')
                ->first();

            // Most problematic SKU this week
            $mostProblematicSku = SkuReview::where('sentiment', 'negative')
                ->where('review_date', '>=', $weekAgo)
                ->select('sku', DB::raw('count(*) as cnt'))
                ->groupBy('sku')
                ->orderByDesc('cnt')
                ->first();

            // Weekly issue trend (last 6 weeks)
            $issueTrend = SkuReview::where('review_date', '>=', Carbon::now()->subWeeks(6)->toDateString())
                ->whereNotNull('issue_category')
                ->select(
                    DB::raw('YEARWEEK(review_date) as week'),
                    'issue_category',
                    DB::raw('count(*) as cnt')
                )
                ->groupBy('week', 'issue_category')
                ->orderBy('week')
                ->get()
                ->groupBy('week');

            return [
                'top_problems'            => $topProblems,
                'most_complained_supplier' => $mostComplainedSupplier,
                'most_problematic_sku'    => $mostProblematicSku,
                'issue_trend'             => $issueTrend,
            ];
        });

        return response()->json($data);
    }

    // -------------------------------------------------------------------------
    // Alerts
    // -------------------------------------------------------------------------

    public function alerts(): JsonResponse
    {
        $alerts = ReviewAlert::open()
            ->with('supplier:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json($alerts);
    }

    public function resolveAlert(int $id): JsonResponse
    {
        $alert = ReviewAlert::findOrFail($id);
        $alert->update(['status' => 'closed']);

        return response()->json(['success' => true, 'message' => 'Alert resolved.']);
    }

    // -------------------------------------------------------------------------
    // CSV Upload
    // -------------------------------------------------------------------------

    public function uploadCsv(Request $request, \App\Services\ReviewFetchService $service): JsonResponse
    {
        if (!$request->hasFile('csv_file') || !$request->file('csv_file')->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid file was uploaded. Please choose a CSV file and try again.',
            ], 422);
        }

        $file      = $request->file('csv_file');
        $extension = strtolower($file->getClientOriginalExtension());

        // Validate by extension instead of MIME — browsers report CSV with many
        // different (and often unreliable) MIME types, which caused valid uploads
        // to be rejected. Accept .csv, .tsv and .txt (we auto-detect delimiter).
        if ($extension !== '' && !in_array($extension, ['csv', 'tsv', 'txt'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file type. Please upload a .csv, .tsv or .txt file.',
            ], 422);
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'message' => 'File is too large. Maximum allowed size is 10MB.',
            ], 422);
        }

        try {
            $rows = $this->parseCsvFile($file->getRealPath());

            if (empty($rows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data rows found. Make sure the file has a header row and at least one review row.',
                ], 422);
            }

            $stats = $service->processCsvRows($rows);

            $message = "Import complete: {$stats['saved']} saved";
            if (!empty($stats['skipped'])) {
                $message .= ", {$stats['skipped']} skipped (duplicates)";
            }
            if (!empty($stats['errors'])) {
                $message .= ", {$stats['errors']} skipped (unknown SKU / invalid row)";
            }
            $message .= '.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'stats'   => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error("ReviewMasterController: CSV upload failed", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse an uploaded CSV/TSV/TXT file into an array of associative rows keyed
     * by the (lower-cased, trimmed) header columns. Auto-detects whether the
     * delimiter is a tab or a comma so files exported as TSV (e.g. copy-pasted
     * from Google Sheets or Excel) are handled correctly.
     */
    private function parseCsvFile(string $path): array
    {
        $rows    = [];
        $headers = [];

        if (($handle = fopen($path, 'r')) === false) {
            return [];
        }

        // Strip UTF-8 BOM if present so the first header isn't named "\xEF\xBB\xBFsku".
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $delimiter = $this->detectDelimiter($handle);

        while (($data = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            // Skip fully empty lines
            if ($data === [null] || (count($data) === 1 && trim((string) $data[0]) === '')) {
                continue;
            }

            if (empty($headers)) {
                $headers = array_map('trim', array_map('strtolower', $data));
                continue;
            }

            // Pad/trim row so it lines up with the header count
            if (count($data) < count($headers)) {
                $data = array_pad($data, count($headers), null);
            } elseif (count($data) > count($headers)) {
                $data = array_slice($data, 0, count($headers));
            }

            $row = array_combine($headers, $data);
            if ($row !== false) {
                $rows[] = $row;
            }
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Sniff the field delimiter by peeking at the first non-empty line. Falls
     * back to comma if neither tabs nor commas are present.
     */
    private function detectDelimiter($handle): string
    {
        $startPos = ftell($handle);

        $sample = '';
        while (($line = fgets($handle)) !== false) {
            if (trim($line) !== '') {
                $sample = $line;
                break;
            }
        }

        // Restore the read pointer so the actual parser sees the full file.
        fseek($handle, $startPos);

        if ($sample === '') {
            return ',';
        }

        $tabCount   = substr_count($sample, "\t");
        $commaCount = substr_count($sample, ',');
        $semiCount  = substr_count($sample, ';');
        $pipeCount  = substr_count($sample, '|');

        $candidates = [
            "\t" => $tabCount,
            ','  => $commaCount,
            ';'  => $semiCount,
            '|'  => $pipeCount,
        ];

        arsort($candidates);
        $best = array_key_first($candidates);

        return $candidates[$best] > 0 ? $best : ',';
    }

    // -------------------------------------------------------------------------
    // Generate AI Reply
    // -------------------------------------------------------------------------

    public function generateReply(int $id, ReviewAnalysisService $service): JsonResponse
    {
        $review = SkuReview::findOrFail($id);
        $reply  = $service->generateReply($review);

        $review->update(['ai_reply' => $reply]);

        return response()->json(['success' => true, 'reply' => $reply]);
    }

    // -------------------------------------------------------------------------
    // Manual Trigger: fetch reviews for a SKU
    // -------------------------------------------------------------------------

    public function triggerFetch(Request $request): JsonResponse
    {
        $sku         = $request->input('sku');
        $marketplace = $request->input('marketplace', 'all');

        if (!$sku) {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }

        if (!ProductMaster::where('sku', $sku)->exists()) {
            return response()->json(['success' => false, 'message' => 'SKU not found in product master.'], 404);
        }

        FetchReviewsJob::dispatch($sku, $marketplace);

        return response()->json([
            'success' => true,
            'message' => "Fetch job dispatched for SKU: {$sku}",
        ]);
    }

    // -------------------------------------------------------------------------
    // Marketplaces list (for filters)
    // -------------------------------------------------------------------------

    public function marketplaces(): JsonResponse
    {
        $list = SkuReview::select('marketplace')
            ->distinct()
            ->orderBy('marketplace')
            ->pluck('marketplace');

        return response()->json($list);
    }

    // -------------------------------------------------------------------------
    // Refresh summary & clear cache (admin trigger)
    // -------------------------------------------------------------------------

    public function refreshSummary(ReviewAnalysisService $service): JsonResponse
    {
        Cache::forget('review_dashboard_stats');
        Cache::forget('review_supplier_intel');
        Cache::forget('review_ai_insights');

        $service->refreshSummaryTable();

        return response()->json(['success' => true, 'message' => 'Summary table refreshed.']);
    }
}
