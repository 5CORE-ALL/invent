<?php

namespace App\Http\Controllers\PerformanceManagement;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class DesignationController extends Controller
{
    /**
     * Check if user is admin
     */
    private function checkAdmin()
    {
        if (Auth::user()->email !== 'president@5core.com') {
            abort(403, 'Unauthorized access. Only admin can manage designations.');
        }
    }

    /**
     * Display a listing of designations (fetched from Users table)
     * Accessible to all authenticated users (for dropdowns)
     */
    public function index()
    {
        try {
            // Fetch unique designations from users table
            $uniqueDesignations = User::where('is_active', true)
                ->whereNotNull('designation')
                ->where('designation', '!=', '')
                ->select('designation')
                ->distinct()
                ->get()
                ->pluck('designation')
                ->filter(function($value) {
                    return !empty($value) && trim($value) !== '';
                })
                ->unique()
                ->values();

            $designationsFromUsers = $uniqueDesignations->map(function($name) {
                try {
                    $userCount = User::where('designation', $name)
                        ->where('is_active', true)
                        ->count();
                } catch (\Exception $e) {
                    $userCount = 0;
                }
                
                return [
                    'id' => $name, // Use name as ID for dynamic designations
                    'name' => $name,
                    'description' => null,
                    'is_active' => true,
                    'is_dynamic' => true, // Flag to indicate it's from users table
                    'user_count' => $userCount,
                ];
            });

            // Also get designations from designations table (if any exist)
            $designationsFromTable = collect([]);
            try {
                if (Schema::hasTable('designations')) {
                    $designationsFromTable = Designation::where('is_active', true)
                        ->get()
                        ->map(function($des) {
                            return [
                                'id' => $des->id,
                                'name' => $des->name,
                                'description' => $des->description,
                                'is_active' => $des->is_active,
                                'is_dynamic' => false,
                                'user_count' => User::where('designation', $des->name)->where('is_active', true)->count(),
                            ];
                        });
                }
            } catch (\Exception $e) {
                // If designations table doesn't exist or has issues, just use users table data
                Log::warning('Could not fetch from designations table: ' . $e->getMessage());
                $designationsFromTable = collect([]);
            }

            // Merge and remove duplicates (prioritize table designations)
            $allDesignations = $designationsFromTable->concat($designationsFromUsers)
                ->unique('name')
                ->sortBy('name')
                ->values();

            return response()->json($allDesignations);
        } catch (\Exception $e) {
            Log::error('Error in DesignationController@index: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to load designations',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created designation
     */
    public function store(Request $request)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:designations,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $designation = Designation::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Designation created successfully',
            'data' => $designation
        ], 201);
    }

    /**
     * Update the specified designation
     */
    public function update(Request $request, Designation $designation)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:designations,name,' . $designation->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $designation->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Designation updated successfully',
            'data' => $designation->fresh()
        ]);
    }

    /**
     * Remove the specified designation
     */
    public function destroy(Designation $designation)
    {
        $this->checkAdmin();

        $designation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Designation deleted successfully'
        ]);
    }
}
