<?php

namespace App\Http\Controllers\PerformanceManagement;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewItem;
use App\Models\User;
use App\Services\PerformanceManagement\PerformanceAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerformanceReviewController extends Controller
{
    protected $analysisService;

    public function __construct(PerformanceAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * Display a listing of performance reviews
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = PerformanceReview::with(['employee', 'reviewer', 'designation']);

            // Filter based on user role
            if ($user->email === 'president@5core.com') {
                // Admin can see all
            } elseif ($user->is5CoreMember()) {
                // Managers can see their team reviews
                $query->where(function($q) use ($user) {
                    $q->where('reviewer_id', $user->id)
                      ->orWhere('employee_id', $user->id);
                });
            } else {
                // Employees can only see their own
                $query->where('employee_id', $user->id);
            }

            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->has('review_period')) {
                $query->where('review_period', $request->review_period);
            }

            $reviews = $query->orderBy('review_date', 'desc')->paginate(20);

            return response()->json($reviews);
        } catch (\Exception $e) {
            Log::error('Error loading performance reviews: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to load reviews',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for creating a new review
     */
    public function create(Request $request)
    {
        $employeeId = $request->employee_id;
        $designationIdOrName = $request->designation_id ?? $request->designation_name;

        if (!$employeeId || !$designationIdOrName) {
            return response()->json(['error' => 'Employee and designation required'], 400);
        }

        $employee = User::findOrFail($employeeId);
        
        // Try to get designation by ID first, then by name
        $designation = null;
        if (is_numeric($designationIdOrName)) {
            $designation = Designation::with([
                'categories.items' => function($query) {
                    $query->where('is_active', true)->orderBy('order');
                }
            ])->find($designationIdOrName);
        }
        
        // If not found by ID, try by name
        if (!$designation) {
            $designation = Designation::where('name', $designationIdOrName)
                ->with([
                    'categories.items' => function($query) {
                        $query->where('is_active', true)->orderBy('order');
                    }
                ])->first();
        }

        // If still not found, use employee's designation
        if (!$designation && $employee->designation) {
            $designation = Designation::where('name', $employee->designation)
                ->with([
                    'categories.items' => function($query) {
                        $query->where('is_active', true)->orderBy('order');
                    }
                ])->first();
        }

        if (!$designation) {
            return response()->json([
                'error' => 'Designation not found. Please create a checklist for this designation first.',
                'employee' => $employee,
                'designation_name' => $employee->designation ?? $designationIdOrName
            ], 404);
        }

        return response()->json([
            'employee' => $employee,
            'designation' => $designation,
        ]);
    }

    /**
     * Store a newly created performance review
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'designation_id' => 'nullable',
            'designation_name' => 'nullable|string',
            'review_period' => 'required|in:Weekly,Monthly,Custom',
            'review_date' => 'required|date',
            'period_start_date' => 'nullable|date',
            'period_end_date' => 'nullable|date',
            'ratings' => 'required|array',
            'ratings.*.checklist_item_id' => 'required|exists:checklist_items,id',
            'ratings.*.rating' => 'required|integer|min:1|max:5',
            'ratings.*.comment' => 'nullable|string',
            'overall_feedback' => 'nullable|string',
        ]);

        // Handle designation - can be ID or name
        $designationId = $validated['designation_id'] ?? null;
        $designationName = $validated['designation_name'] ?? null;
        $employee = User::findOrFail($validated['employee_id']);

        // If designation_id is not numeric, treat it as name
        if ($designationId && !is_numeric($designationId)) {
            $designationName = $designationId;
            $designationId = null;
        }

        // If no designation provided, use employee's designation
        if (!$designationId && !$designationName && $employee->designation) {
            $designationName = $employee->designation;
        }

        // Find or create designation
        if ($designationName) {
            $designation = Designation::firstOrCreate(
                ['name' => $designationName],
                ['description' => null, 'is_active' => true]
            );
            $designationId = $designation->id;
        } elseif (!$designationId) {
            return response()->json([
                'success' => false,
                'message' => 'Designation is required'
            ], 422);
        }

        $finalDesignationId = $designationId;
        
        $review = DB::transaction(function() use ($validated, $finalDesignationId) {
            $review = PerformanceReview::create([
                'employee_id' => $validated['employee_id'],
                'reviewer_id' => Auth::id(),
                'designation_id' => $finalDesignationId,
                'review_period' => $validated['review_period'],
                'review_date' => $validated['review_date'],
                'period_start_date' => $validated['period_start_date'] ?? null,
                'period_end_date' => $validated['period_end_date'] ?? null,
                'overall_feedback' => $validated['overall_feedback'] ?? null,
                'is_completed' => true,
            ]);

            // Create review items
            foreach ($validated['ratings'] as $ratingData) {
                PerformanceReviewItem::create([
                    'review_id' => $review->id,
                    'checklist_item_id' => $ratingData['checklist_item_id'],
                    'rating' => $ratingData['rating'],
                    'comment' => $ratingData['comment'] ?? null,
                ]);
            }

            // Calculate scores
            $review->calculateScores();

            // Generate AI feedback
            $employee = User::find($validated['employee_id']);
            $aiFeedback = $this->analysisService->generateFeedback($review, $employee);
            $review->ai_feedback = $aiFeedback;
            $review->save();

            return $review->load(['employee', 'reviewer', 'designation', 'reviewItems.checklistItem']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Performance review created successfully',
            'data' => $review
        ], 201);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        Log::error('Error creating performance review: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'request' => $request->all()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error creating review: ' . $e->getMessage()
        ], 500);
    }
    }

    /**
     * Display the specified review
     * @param int|string $id Review ID from URL
     */
    public function show($id)
    {
        try {
            $user = Auth::user();

            // Find review by ID (include soft-deleted so we can return 404 with proper message)
            $review = PerformanceReview::with([
                'employee',
                'reviewer',
                'designation',
                'reviewItems.checklistItem.category'
            ])->find($id);

            if (!$review) {
                return response()->json([
                    'error' => 'Review not found',
                    'message' => 'No review found with the given ID.'
                ], 404);
            }

            // Check access
            if ($user->email !== 'president@5core.com' &&
                $review->employee_id !== $user->id &&
                $review->reviewer_id !== $user->id &&
                !$user->is5CoreMember()) {
                return response()->json([
                    'error' => 'Unauthorized access'
                ], 403);
            }

            // Log for debugging
            Log::info('Review data being returned', [
                'review_id' => $review->id,
                'has_review_items' => $review->reviewItems->count(),
                'employee_name' => $review->employee->name ?? null,
                'reviewer_name' => $review->reviewer->name ?? null,
            ]);

            return response()->json($review);
        } catch (\Exception $e) {
            Log::error('Error loading performance review: ' . $e->getMessage(), [
                'review_id' => $id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to load review',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get review statistics for an employee
     */
    public function getEmployeeStats($employeeId)
    {
        $user = Auth::user();
        $employee = User::find($employeeId);

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Same access rules as chart data
        if ($user->email !== 'president@5core.com' &&
            !$user->is5CoreMember() &&
            (int) $employeeId !== (int) $user->id &&
            !PerformanceReview::where('reviewer_id', $user->id)
                ->where('employee_id', $employeeId)
                ->exists()) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        $reviews = PerformanceReview::where('employee_id', $employeeId)
            ->where('is_completed', true)
            ->orderBy('review_date', 'desc')
            ->get();

        $latestReview = $reviews->first();
        $previousReview = $reviews->skip(1)->first();

        $stats = [
            'total_reviews' => $reviews->count(),
            'latest_score' => $latestReview ? (float) $latestReview->normalized_score : null,
            'previous_score' => $previousReview ? (float) $previousReview->normalized_score : null,
            'score_change' => $latestReview && $previousReview
                ? round((float) $latestReview->normalized_score - (float) $previousReview->normalized_score, 2)
                : null,
            'average_score' => $reviews->count() ? round((float) $reviews->avg('normalized_score'), 2) : null,
            'performance_level' => $latestReview ? $latestReview->performance_level : null,
            'predicted_score' => $this->analysisService->predictNextScore($employee),
        ];

        return response()->json($stats);
    }
}
