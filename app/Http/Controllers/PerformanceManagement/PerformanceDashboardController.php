<?php

namespace App\Http\Controllers\PerformanceManagement;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\PerformanceManagement\PerformanceAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PerformanceDashboardController extends Controller
{
    protected $analysisService;

    public function __construct(PerformanceAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * Employee dashboard - view own performance
     */
    public function employeeDashboard()
    {
        $employee = Auth::user();
        
        $latestReview = PerformanceReview::where('employee_id', $employee->id)
            ->where('is_completed', true)
            ->orderBy('review_date', 'desc')
            ->first();

        $reviews = PerformanceReview::where('employee_id', $employee->id)
            ->where('is_completed', true)
            ->orderBy('review_date', 'desc')
            ->limit(10)
            ->get();

        // Get trend data
        $trendData = $this->analysisService->getTrendData($employee);
        $movingAverageData = $this->analysisService->getMovingAverageData($employee);
        $categoryScores = $latestReview ? $this->analysisService->getCategoryScores($latestReview) : collect([]);

        // Get predicted score
        $predictedScore = $this->analysisService->predictNextScore($employee);

        // Get weak areas
        $weakAreas = $categoryScores->where('avg_score', '<', 3)->take(3);

        return view('pages.performance-dashboard', compact(
            'employee',
            'latestReview',
            'reviews',
            'trendData',
            'movingAverageData',
            'categoryScores',
            'predictedScore',
            'weakAreas'
        ));
    }

    /**
     * Manager dashboard - view team performance
     */
    public function managerDashboard()
    {
        $manager = Auth::user();
        
        $teamReviews = PerformanceReview::where('reviewer_id', $manager->id)
            ->with(['employee', 'designation'])
            ->orderBy('review_date', 'desc')
            ->limit(20)
            ->get();

        $teamMembers = User::whereHas('performanceReviews', function($query) use ($manager) {
            $query->where('reviewer_id', $manager->id);
        })->get();

        return view('pages.performance-manager-dashboard', compact('teamReviews', 'teamMembers'));
    }

    /**
     * Admin dashboard - view all performance
     */
    public function adminDashboard()
    {
        if (Auth::user()->email !== 'president@5core.com') {
            abort(403, 'Unauthorized access');
        }

        $totalReviews = PerformanceReview::where('is_completed', true)->count();
        $totalEmployees = User::where('is_active', true)->count();
        $designations = Designation::withCount('performanceReviews')->get();

        $recentReviews = PerformanceReview::with(['employee', 'reviewer', 'designation'])
            ->orderBy('review_date', 'desc')
            ->limit(20)
            ->get();

        return view('pages.performance-admin-dashboard', compact(
            'totalReviews',
            'totalEmployees',
            'designations',
            'recentReviews'
        ));
    }

    /**
     * Get chart data for employee
     */
    public function getChartData($employeeId)
    {
        $employee = User::findOrFail($employeeId);
        
        // Check access (president, self, reviewer of employee, or any @5core.com member)
        $user = Auth::user();
        if ($user->email !== 'president@5core.com' &&
            !$user->is5CoreMember() &&
            $employee->id !== $user->id &&
            !PerformanceReview::where('reviewer_id', $user->id)
                ->where('employee_id', $employeeId)
                ->exists()) {
            abort(403, 'Unauthorized access');
        }

        $trendData = $this->analysisService->getTrendData($employee);
        $movingAverageData = $this->analysisService->getMovingAverageData($employee);

        $latestReview = PerformanceReview::where('employee_id', $employeeId)
            ->where('is_completed', true)
            ->orderBy('review_date', 'desc')
            ->first();

        $categoryScores = $this->analysisService->getCategoryScores($latestReview);

        return response()->json([
            'trend' => $trendData->values(),
            'moving_average' => $movingAverageData->values(),
            'category_scores' => $categoryScores->values(),
        ]);
    }

    /**
     * Create review interface for manager
     */
    public function createReview()
    {
        $employees = User::where('is_active', true)
            ->where('id', '!=', Auth::id())
            ->orderBy('name')
            ->get();

        // Get designations from both table and users
        $designationsFromTable = Designation::where('is_active', true)
            ->orderBy('name')
            ->get();

        $designationsFromUsers = User::where('is_active', true)
            ->whereNotNull('designation')
            ->where('designation', '!=', '')
            ->distinct()
            ->pluck('designation')
            ->map(function($name) {
                return (object)[
                    'id' => $name,
                    'name' => $name,
                    'description' => null,
                    'is_active' => true,
                    'is_dynamic' => true,
                ];
            });

        // Merge and remove duplicates
        $allDesignations = $designationsFromTable->concat($designationsFromUsers)
            ->unique('name')
            ->sortBy('name')
            ->values();

        return view('pages.performance-create-review', compact('employees', 'allDesignations'));
    }
}
