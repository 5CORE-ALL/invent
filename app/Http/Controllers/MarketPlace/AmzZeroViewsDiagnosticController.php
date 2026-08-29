<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Services\AmazonZeroViewsDiagnosticService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmzZeroViewsDiagnosticController extends Controller
{
    public function __construct(private AmazonZeroViewsDiagnosticService $service)
    {
        parent::__construct();
    }

    public function index(): View
    {
        return view('market-places.amz_zero_views_diagnostic', [
            'filterOptions' => $this->service->filterOptions(),
            'runStatus' => AmazonZeroViewsDiagnosticService::status(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        try {
            $payload = $this->service->paginate($this->filtersFromRequest($request));

            return response()->json([
                'success' => true,
                'data' => $payload['data'],
                'last_page' => $payload['last_page'],
                'total' => $payload['total'],
                'summary' => $payload['summary'],
                'run' => AmazonZeroViewsDiagnosticService::status(),
                'meta' => [
                    'marketplace' => $this->service->marketplaceLabel(),
                    'account' => $this->service->accountLabel(),
                    'page' => $payload['page'],
                    'size' => $payload['size'],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('AmzZeroViewsDiagnostic data failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'data' => [],
                'last_page' => 1,
                'total' => 0,
                'summary' => [],
                'message' => 'Unavailable',
            ], 500);
        }
    }

    public function detail(Request $request, ?string $asin = null): JsonResponse
    {
        $term = trim((string) ($request->query('sku') ?: $request->query('asin') ?: $asin ?: ''));
        if ($term === '') {
            return response()->json([
                'success' => false,
                'message' => 'SKU or ASIN is required.',
            ], 422);
        }

        try {
            $row = $this->service->detail($term);
            if ($row === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $row,
            ]);
        } catch (\Throwable $e) {
            Log::error('AmzZeroViewsDiagnostic detail failed', [
                'term' => $term,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unavailable',
            ], 500);
        }
    }

    public function filterOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->filterOptions(),
        ]);
    }

    public function startRun(Request $request, ?string $asin = null): JsonResponse
    {
        if ($asin && ! $request->filled('asin')) {
            $request->merge([
                'asin' => $asin,
                'mode' => $request->input('mode', 'single'),
            ]);
        }

        $validated = $request->validate([
            'mode' => 'nullable|in:all,selected,single',
            'sku' => 'nullable|string|max:255',
            'asin' => 'nullable|string|max:32',
            'skus' => 'nullable|array',
            'skus.*' => 'string|max:255',
            'zero_only' => 'nullable',
            'filters' => 'nullable|array',
        ]);

        $status = AmazonZeroViewsDiagnosticService::status();
        $probe = Cache::lock(AmazonZeroViewsDiagnosticService::CACHE_LOCK_KEY, 5);
        $lockFree = $probe->get();
        if ($lockFree) {
            $probe->release();
        }
        if (! empty($status['running']) && ! $lockFree) {
            return response()->json([
                'success' => true,
                'already_running' => true,
                'status' => $status,
                'message' => $status['message'] ?? 'Diagnostic already running',
            ]);
        }

        $mode = $validated['mode'] ?? 'all';
        $filters = $validated['filters'] ?? $this->filtersFromRequest($request);
        $skus = [];
        if ($mode === 'single') {
            $sku = trim((string) ($validated['sku'] ?? ''));
            $asin = trim((string) ($validated['asin'] ?? ''));
            if ($sku !== '') {
                $skus = [$sku];
            } elseif ($asin !== '') {
                $detail = $this->service->detail($asin);
                if (! empty($detail['sku'])) {
                    $skus = [$detail['sku']];
                }
            }
            if ($skus === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU or ASIN is required for a single diagnostic.',
                ], 422);
            }
        } elseif ($mode === 'selected') {
            $skus = array_values(array_unique(array_filter(array_map(
                static fn ($s) => trim((string) $s),
                $validated['skus'] ?? []
            ))));
            if ($skus === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Select at least one SKU.',
                ], 422);
            }
        }

        $payload = [
            'skus' => $skus,
            'sku' => $validated['sku'] ?? null,
            'asin' => $validated['asin'] ?? null,
            'filters' => $filters,
            'zero_only' => $this->truthy($validated['zero_only'] ?? ($filters['zero_only'] ?? false)),
            'all' => $mode === 'all' && ! $this->truthy($filters['zero_only'] ?? false),
        ];

        $dir = storage_path('app/zero-views-diagnostic');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $payloadFile = $dir.'/run-'.uniqid('', true).'.json';
        file_put_contents($payloadFile, json_encode($payload));

        AmazonZeroViewsDiagnosticService::writeStatus([
            'running' => true,
            'status' => 'Queued',
            'total' => 0,
            'done' => 0,
            'ok' => 0,
            'fail' => 0,
            'started_at' => now()->toDateTimeString(),
            'finished_at' => null,
            'message' => 'Queued Amazon 0 Views Diagnostic…',
        ]);

        $php = PHP_BINARY ?: 'php';
        $artisan = base_path('artisan');
        $args = [$php, $artisan, 'amazon:run-zero-views-diagnostic', '--payload-file='.$payloadFile];
        $cmd = implode(' ', array_map('escapeshellarg', $args));
        $logFile = storage_path('logs/amazon-zero-views-diagnostic.log');
        $cleanup = '; rm -f '.escapeshellarg($payloadFile);
        $full = '('.$cmd.$cleanup.') >> '.escapeshellarg($logFile).' 2>&1 &';

        try {
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                pclose(popen('start /B '.$cmd.' >> '.escapeshellarg($logFile).' 2>&1', 'r'));
            } else {
                exec($full);
            }
        } catch (\Throwable $e) {
            Log::error('AmzZeroViewsDiagnostic startRun spawn failed', ['error' => $e->getMessage()]);
            AmazonZeroViewsDiagnosticService::writeStatus([
                'running' => false,
                'status' => 'Failed',
                'message' => 'Failed to start: '.$e->getMessage(),
                'finished_at' => now()->toDateTimeString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Retry Required',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Diagnostic queued',
            'status' => AmazonZeroViewsDiagnosticService::status(),
        ]);
    }

    public function runStatus(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => AmazonZeroViewsDiagnosticService::status(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filtersFromRequest($request);
        $filename = 'amazon-zero-views-diagnostic-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Parent',
                'SKU',
                'ASIN',
                'Buyer Link',
                'Seller Link',
                'INV',
                'INV AMZ',
                'OV L30',
                'Dil %',
                'View L30',
                'View L7',
                'A L30',
                'CVR L30',
                'Price',
                'LMP',
                'GROI %',
                'Listing Status',
                'Suppression',
                'Buyable',
                'Ad Present',
                'Diagnostic Result',
                'Problem',
                'Recommended Action',
                'Last Checked',
            ]);

            foreach ($this->service->evaluateFiltered($filters) as $row) {
                fputcsv($out, [
                    $row['Parent'] ?? $row['parent'] ?? '',
                    $row['sku'] ?? '',
                    $row['asin'] ?? '',
                    $row['buyer_link'] ?? '',
                    $row['seller_link'] ?? '',
                    $row['INV'] ?? $row['inventory'] ?? '',
                    $row['INV_AMZ'] ?? $row['amazon_inventory'] ?? '',
                    $row['L30'] ?? '',
                    $row['E Dil%'] ?? '',
                    $row['Sess30'] ?? $row['l30_views'] ?? '',
                    $row['Sess7'] ?? $row['l7_views'] ?? '',
                    $row['A_L30'] ?? '',
                    $row['CVR_L30'] ?? '',
                    $row['price'] ?? $row['std_price'] ?? '',
                    $row['lmp_price'] ?? '',
                    $row['GROI%'] ?? '',
                    $row['listing_status'] ?? '',
                    $row['suppression'] ?? '',
                    $row['buyable'] ?? '',
                    ! empty($row['ad_present']) ? 'Yes' : 'No',
                    $row['diagnostic_status'] ?? '',
                    $row['problem'] ?? '',
                    $row['recommended_action'] ?? '',
                    $row['last_checked_at'] ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'page' => $request->input('page', 1),
            'size' => $request->input('size', 120),
            'marketplace' => $request->input('marketplace'),
            'account' => $request->input('account'),
            'sku' => $request->input('sku'),
            'asin' => $request->input('asin'),
            'brand' => $request->input('brand'),
            'category' => $request->input('category'),
            'status' => $request->input('status'),
            'diagnostic_result' => $request->input('diagnostic_result'),
            'l30_views' => $request->input('l30_views'),
            'inv' => $request->input('inv'),
            'zero_only' => $request->has('zero_only') ? $request->input('zero_only') : 0,
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'card' => $request->input('card'),
        ];
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
