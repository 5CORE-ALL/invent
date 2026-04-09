<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\FollowUpDashboardQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmDashboardController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $assigneeId = $request->filled('assigned_user_id')
            ? $request->integer('assigned_user_id')
            : null;

        $conversion = FollowUpDashboardQueries::conversionStats($assigneeId);

        $payload = [
            'counts' => [
                'pending' => FollowUpDashboardQueries::pendingFollowUps($assigneeId)->count(),
                'overdue' => FollowUpDashboardQueries::overdueFollowUps($assigneeId)->count(),
                'today' => FollowUpDashboardQueries::todaysFollowUps($assigneeId)->count(),
            ],
            'conversion' => $conversion,
            'lists' => [
                'overdue' => FollowUpDashboardQueries::overdueFollowUps($assigneeId)
                    ->with(['customer:id,name', 'assignedUser:id,name'])
                    ->limit(15)
                    ->get(),
                'today' => FollowUpDashboardQueries::todaysFollowUps($assigneeId)
                    ->with(['customer:id,name', 'assignedUser:id,name'])
                    ->limit(15)
                    ->get(),
            ],
        ];

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json($payload);
        }

        return view('crm.dashboard', $payload);
    }
}
