<?php

namespace App\Http\Controllers\AmazonAds;

use App\Http\Controllers\Controller;
use App\Models\AmazonAdsPushLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AmazonAdsPushLogController extends Controller
{
    /**
     * Display the push logs page
     */
    public function index(Request $request)
    {
        return view('amazon-ads.push-logs.index');
    }

    /**
     * Get push logs data for DataTables/Tabulator
     */
    public function getData(Request $request): JsonResponse
    {
        $query = AmazonAdsPushLog::query()->with('user:id,name');

        // Filters
        if ($request->filled('push_type')) {
            $query->where('push_type', $request->push_type);
        }

        if ($request->filled('status')) {
            if ($request->status === 'failed') {
                // "failed" filter shows both failed and skipped
                $query->whereIn('status', ['failed', 'skipped']);
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', 'LIKE', '%' . $request->campaign_id . '%');
        }

        if ($request->filled('campaign_name')) {
            $query->where('campaign_name', 'LIKE', '%' . $request->campaign_name . '%');
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // DataTables search
        if ($request->filled('search.value')) {
            $search = $request->input('search.value');
            $query->where(function($q) use ($search) {
                $q->where('campaign_id', 'LIKE', "%{$search}%")
                  ->orWhere('campaign_name', 'LIKE', "%{$search}%")
                  ->orWhere('reason', 'LIKE', "%{$search}%");
            });
        }

        // Total records
        $totalRecords = AmazonAdsPushLog::count();
        $totalFiltered = $query->count();

        // DataTables ordering
        if ($request->filled('order.0.column')) {
            $columns = ['created_at', 'push_type', 'campaign_id', 'campaign_name', 'value', 'status', 'reason', 'source'];
            $orderColumn = $columns[$request->input('order.0.column')] ?? 'created_at';
            $orderDir = $request->input('order.0.dir', 'desc');
            $query->orderBy($orderColumn, $orderDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $start = $request->input('start', 0);
        $length = $request->input('length', 25);
        $logs = $query->skip($start)->take($length)->get();

        // Format data for DataTables
        $data = $logs->map(function($log) {
            $statusColors = [
                'success' => 'success',
                'skipped' => 'warning',
                'failed' => 'danger',
            ];
            $statusColor = $statusColors[$log->status] ?? 'secondary';
            
            return [
                'id' => $log->id,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                'push_type' => $log->push_type,
                'push_type_name' => '<span class="badge bg-primary">' . strtoupper(str_replace('_', ' ', $log->push_type)) . '</span>',
                'campaign_id' => $log->campaign_id ?? '<span class="text-muted">N/A</span>',
                'campaign_name' => $log->campaign_name 
                    ? '<span class="text-truncate d-inline-block" style="max-width: 200px;" title="' . htmlspecialchars($log->campaign_name) . '">' . htmlspecialchars($log->campaign_name) . '</span>'
                    : '<span class="text-muted">N/A</span>',
                'value' => $log->value ? '$' . number_format($log->value, 2) : '<span class="text-muted">N/A</span>',
                'status' => $log->status,
                'status_badge' => '<span class="badge bg-' . $statusColor . '">' . strtoupper($log->status) . '</span>',
                'reason' => $log->reason 
                    ? '<span class="text-truncate d-inline-block" style="max-width: 250px;" title="' . htmlspecialchars($log->reason) . '">' . htmlspecialchars($log->reason) . '</span>'
                    : '<span class="text-muted">N/A</span>',
                'source' => '<span class="badge bg-secondary">' . strtoupper($log->source) . '</span>',
                'http_status' => $log->http_status,
                'request_data' => $log->request_data,
                'response_data' => $log->response_data,
                'actions' => '<button type="button" class="btn btn-sm btn-info view-details" data-log=\'' . json_encode([
                    'id' => $log->id,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'push_type_name' => strtoupper(str_replace('_', ' ', $log->push_type)),
                    'campaign_id' => $log->campaign_id,
                    'campaign_name' => $log->campaign_name,
                    'value' => $log->value,
                    'status' => $log->status,
                    'status_badge' => '<span class="badge bg-' . $statusColor . '">' . strtoupper($log->status) . '</span>',
                    'reason' => $log->reason,
                    'source' => $log->source,
                    'http_status' => $log->http_status,
                    'request_data' => $log->request_data,
                    'response_data' => $log->response_data,
                ]) . '\'><i class="mdi mdi-eye"></i></button>',
            ];
        });

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Get statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subDays(7)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        $stats = AmazonAdsPushLog::getStats($dateFrom, $dateTo);

        // Get stats by push type
        $statsByType = [];
        $pushTypes = ['sp_sbid', 'sb_sbid', 'sp_sbgt', 'sb_sbgt'];
        
        foreach ($pushTypes as $type) {
            $typeQuery = AmazonAdsPushLog::ofType($type)
                ->where('created_at', '>=', $dateFrom)
                ->where('created_at', '<=', $dateTo . ' 23:59:59');
            
            $total = $typeQuery->count();
            $success = (clone $typeQuery)->where('status', 'success')->count();
            $skipped = (clone $typeQuery)->where('status', 'skipped')->count();
            $failed = (clone $typeQuery)->where('status', 'failed')->count();

            $statsByType[$type] = [
                'total' => $total,
                'success' => $success,
                'skipped' => $skipped,
                'failed' => $failed,
                'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
            ];
        }

        // Get most common reasons for failures
        $topReasons = AmazonAdsPushLog::failed()
            ->where('created_at', '>=', $dateFrom)
            ->where('created_at', '<=', $dateTo . ' 23:59:59')
            ->selectRaw('reason, COUNT(*) as count')
            ->groupBy('reason')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return response()->json([
            'overall' => $stats,
            'by_type' => $statsByType,
            'top_reasons' => $topReasons,
        ]);
    }

    /**
     * Export failed campaigns
     */
    public function export(Request $request)
    {
        $query = AmazonAdsPushLog::failed();

        // Apply filters
        if ($request->filled('push_type')) {
            $query->where('push_type', $request->push_type);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $filename = 'amazon_ads_failed_campaigns_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            
            // CSV Header
            fputcsv($file, [
                'Date',
                'Push Type',
                'Campaign ID',
                'Campaign Name',
                'Value',
                'Status',
                'Reason',
                'Source',
                'User',
            ]);

            // Data rows
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->push_type_name,
                    $log->campaign_id ?? 'N/A',
                    $log->campaign_name ?? 'N/A',
                    $log->value ?? 'N/A',
                    $log->status,
                    $log->reason ?? 'N/A',
                    $log->source,
                    $log->user->name ?? 'System',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Delete old logs
     */
    public function cleanup(Request $request): JsonResponse
    {
        $days = $request->get('days', 90);
        
        $deleted = AmazonAdsPushLog::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deleted} log records older than {$days} days.",
            'deleted' => $deleted,
        ]);
    }
}
