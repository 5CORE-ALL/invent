<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\RrPortfolioUser;
use App\Models\User;
use App\Services\TeamLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class UserController extends Controller
{
    public function index()
    {
        // Active users only (not deactivated)
        // select() must run before withCount(): a later select() replaces columns and drops the count subquery.
        $users = User::query()
            ->where('is_active', true)
            ->select('id', 'name', 'phone', 'email', 'designation')
            ->with(['userRR', 'userSalary'])
            ->withCount('rrPortfolioAssignments')
            ->orderBy('name')
            ->get();

        // Get inactive users (is_active = false)
        $inactiveUsers = User::query()
            ->where('is_active', false)
            ->select('id', 'name', 'phone', 'email', 'designation', 'deactivated_at')
            ->with(['userRR', 'userSalary'])
            ->withCount('rrPortfolioAssignments')
            ->orderByDesc('deactivated_at')
            ->get();

        // Calculate total salary PP for active users
        $totalSalaryPP = $users->sum(function($user) {
            return $user->userSalary?->salary_pp ?? 0;
        });

        // Calculate total increment for active users
        $totalIncrement = $users->sum(function($user) {
            return $user->userSalary?->increment ?? 0;
        });

        // Fetch TeamLogger data for previous month
        $previousMonth = Carbon::now()->subMonth()->format('F Y');
        $teamLoggerService = new TeamLoggerService();
        $teamLoggerData = $teamLoggerService->fetchByMonth($previousMonth, true);

        // Email mapping: Map user email to TeamLogger email if different
        $emailMapping = [
            'adexec1@5core.com' => 'support@prolightsounds.com',
        ];

        // Check if current user has edit permission
        $canEdit = auth()->check() && in_array(auth()->user()->email, ['president@5core.com', 'hr@5core.com']);

        return view('pages.add-user', compact('users', 'inactiveUsers', 'canEdit', 'totalSalaryPP', 'totalIncrement', 'teamLoggerData', 'previousMonth', 'emailMapping'));
    }

    public function update(Request $request, User $user)
    {
        // Check permission - only president@5core.com and hr@5core.com can edit
        if (!in_array(auth()->user()->email, ['president@5core.com', 'hr@5core.com'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit user data.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'designation' => 'nullable|string|max:255',
            'rr_role' => 'nullable|string|max:65535',
            'training' => 'nullable|string|max:65535',
            'resources' => 'nullable|string|max:65535',
            'salary_pp' => 'nullable|numeric|min:0',
            'increment' => 'nullable|numeric|min:0',
            'other' => 'nullable|numeric|min:0',
            'adv_inc_other' => 'nullable|numeric|min:0',
            'bank_1' => 'nullable|string|max:65535',
            'bank_2' => 'nullable|string|max:65535',
            'upi_id' => 'nullable|string|max:65535',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->email = $request->email;
        $user->designation = $request->designation;
        $user->save();

        $user->userRR()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'role' => $request->input('rr_role'),
                'training' => $request->input('training'),
                'resources' => $request->input('resources'),
            ]
        );
        $user->load('userRR');

        $user->userSalary()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'salary_pp' => $request->input('salary_pp'),
                'increment' => $request->input('increment'),
                'other' => $request->input('other'),
                'adv_inc_other' => $request->input('adv_inc_other'),
                'bank_1' => $request->input('bank_1'),
                'bank_2' => $request->input('bank_2'),
                'upi_id' => $request->input('upi_id'),
            ]
        );
        $user->load('userSalary');

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'designation' => $user->designation,
                'rr_role' => $user->userRR->role ?? '',
                'training' => $user->userRR->training ?? '',
                'resources' => $user->userRR->resources ?? '',
                'salary_pp' => $user->userSalary->salary_pp ?? '',
                'increment' => $user->userSalary->increment ?? '',
                'other' => $user->userSalary->other ?? '',
                'adv_inc_other' => $user->userSalary->adv_inc_other ?? '',
                'bank_1' => $user->userSalary->bank_1 ?? '',
                'bank_2' => $user->userSalary->bank_2 ?? '',
                'upi_id' => $user->userSalary->upi_id ?? '',
                'has_rr_portfolio' => RrPortfolioUser::where('user_id', $user->id)->exists(),
            ]
        ]);
    }

    public function destroy(User $user)
    {
        if (!in_array(auth()->user()->email, ['president@5core.com', 'hr@5core.com'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete users.'
            ], 403);
        }

        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.'
            ], 422);
        }

        $user->is_active = false;
        $user->deactivated_at = now();
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User deactivated. They cannot sign in until recovered.',
        ]);
    }

    /**
     * Get active users for API
     */
    public function getActiveUsers()
    {
        $users = User::where('is_active', true)
            ->with(['userRR', 'userSalary'])
            ->select('id', 'name', 'phone', 'email', 'designation')
            ->orderBy('name')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'designation' => $user->designation,
                    'rr_role' => $user->userRR->role ?? '',
                    'training' => $user->userRR->training ?? '',
                    'resources' => $user->userRR->resources ?? '',
                    'salary_pp' => $user->userSalary->salary_pp ?? '',
                    'increment' => $user->userSalary->increment ?? '',
                    'other' => $user->userSalary->other ?? '',
                    'adv_inc_other' => $user->userSalary->adv_inc_other ?? '',
                    'bank_1' => $user->userSalary->bank_1 ?? '',
                    'bank_2' => $user->userSalary->bank_2 ?? '',
                ];
            });

        return response()->json($users);
    }

    public function restore(int $id)
    {
        if (!in_array(auth()->user()->email, ['president@5core.com', 'hr@5core.com'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to restore users.'
            ], 403);
        }

        $user = User::query()->where('is_active', false)->findOrFail($id);
        $user->is_active = true;
        $user->deactivated_at = null;
        $user->save();
        $user->load(['userRR', 'userSalary']);

        return response()->json([
            'success' => true,
            'message' => 'User restored successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'designation' => $user->designation,
                'rr_role' => $user->userRR->role ?? '',
                'training' => $user->userRR->training ?? '',
                'resources' => $user->userRR->resources ?? '',
                'salary_pp' => $user->userSalary->salary_pp ?? '',
                'increment' => $user->userSalary->increment ?? '',
                'other' => $user->userSalary->other ?? '',
                'adv_inc_other' => $user->userSalary->adv_inc_other ?? '',
                'bank_1' => $user->userSalary->bank_1 ?? '',
                'bank_2' => $user->userSalary->bank_2 ?? '',
            ]
        ]);
    }

    public function copySalaryLmToPp()
    {
        // Check permission
        if (!in_array(auth()->user()->email, ['president@5core.com', 'hr@5core.com'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this operation.'
            ], 403);
        }

        try {
            // Get all active users with salaries
            $users = User::query()
                ->where('is_active', true)
                ->with(['userSalary'])
                ->get();

            $updatedCount = 0;
            foreach ($users as $user) {
                if ($user->userSalary) {
                    $salaryPP = $user->userSalary->salary_pp ?? 0;
                    $increment = $user->userSalary->increment ?? 0;
                    $salaryLM = $salaryPP + $increment;

                    // Update Salary PP with Salary LM value
                    if ($salaryLM > 0) {
                        $user->userSalary->salary_pp = $salaryLM;
                        $user->userSalary->increment = 0; // Reset increment
                        $user->userSalary->save();
                        $updatedCount++;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} user(s). Salary LM values copied to Salary PP and increments reset.",
                'updated_count' => $updatedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    public function importData(Request $request)
    {
        // Check permission
        if (!in_array(auth()->user()->email, ['president@5core.com', 'hr@5core.com'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to import data.'
            ], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048'
        ]);

        try {
            $file = $request->file('file');
            $csvData = array_map('str_getcsv', file($file->getRealPath()));
            
            // Remove header row
            $header = array_shift($csvData);
            
            $updated = 0;
            $notFound = [];
            $errors = [];
            
            foreach ($csvData as $row) {
                if (count($row) < 2) continue;
                
                $name = trim($row[0]);
                $salaryPP = isset($row[1]) ? trim($row[1]) : '';
                $increment = isset($row[2]) ? trim($row[2]) : '';
                $other = isset($row[3]) ? trim($row[3]) : '';
                $advIncOther = isset($row[4]) ? trim($row[4]) : '';
                
                if (empty($name)) continue;
                
                // Find user by name
                $user = User::where('name', $name)
                    ->where('is_active', true)
                    ->first();
                
                if ($user) {
                    // Prepare data array with only non-empty values
                    $salaryData = [];
                    if ($salaryPP !== '') $salaryData['salary_pp'] = $salaryPP;
                    if ($increment !== '') $salaryData['increment'] = $increment;
                    if ($other !== '') $salaryData['other'] = $other;
                    if ($advIncOther !== '') $salaryData['adv_inc_other'] = $advIncOther;
                    
                    // Update or create salary only if there's data to update
                    if (!empty($salaryData)) {
                        $user->userSalary()->updateOrCreate(
                            ['user_id' => $user->id],
                            $salaryData
                        );
                        $updated++;
                    }
                } else {
                    $notFound[] = $name;
                }
            }
            
            $message = "Successfully updated {$updated} user(s).";
            if (count($notFound) > 0) {
                $message .= " " . count($notFound) . " user(s) not found: " . implode(', ', array_slice($notFound, 0, 5));
                if (count($notFound) > 5) {
                    $message .= " and " . (count($notFound) - 5) . " more...";
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'updated' => $updated,
                'not_found' => count($notFound)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing file: ' . $e->getMessage()
            ], 500);
        }
    }

    public function importBanksData(Request $request)
    {
        // Check permission
        if (!in_array(auth()->user()->email, ['president@5core.com', 'hr@5core.com'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to import data.'
            ], 403);
        }

        // Validate file
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        try {
            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());
            $lines = explode("\n", $content);
            
            $updated = 0;
            $errors = [];
            $skipped = 0;
            
            foreach ($lines as $index => $line) {
                // Skip header row
                if ($index === 0) continue;
                
                // Skip empty lines
                $line = trim($line);
                if (empty($line)) continue;
                
                // Parse CSV line
                $data = str_getcsv($line);
                
                // Validate format (need at least 3 columns: Name, Bank 1, Bank 2)
                if (count($data) < 3) {
                    $errors[] = "Row " . ($index + 1) . ": Invalid format";
                    continue;
                }
                
                $name = trim($data[0]);
                $bank1 = trim($data[1]);
                $bank2 = trim($data[2]);
                $upiId = isset($data[3]) ? trim($data[3]) : '';
                
                // Find user by name
                $user = User::where('name', $name)->first();
                
                if (!$user) {
                    $errors[] = "Row " . ($index + 1) . ": User '$name' not found";
                    continue;
                }
                
                // Update or create user salary record with bank data
                $updateData = [
                    'bank_1' => $bank1,
                    'bank_2' => $bank2,
                ];
                
                // Only update UPI ID if it's provided
                if ($upiId !== '') {
                    $updateData['upi_id'] = $upiId;
                }
                
                $user->userSalary()->updateOrCreate(
                    ['user_id' => $user->id],
                    $updateData
                );
                
                $updated++;
            }
            
            // Prepare response message
            $message = "Import completed: $updated user(s) updated";
            if (count($errors) > 0) {
                $message .= ". " . count($errors) . " error(s) occurred.";
                if (count($errors) <= 5) {
                    $message .= " (" . implode('; ', $errors) . ")";
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'stats' => [
                    'updated' => $updated,
                    'errors' => count($errors),
                    'skipped' => $skipped
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing file: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportData()
    {
        // Check permission
        if (!in_array(auth()->user()->email, ['president@5core.com', 'hr@5core.com'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to export data.'
            ], 403);
        }

        // Get active users with salary and TeamLogger data
        $users = User::query()
            ->where('is_active', true)
            ->select('id', 'name', 'email')
            ->with(['userSalary'])
            ->orderBy('name')
            ->get();

        // Fetch TeamLogger data for previous month
        $previousMonth = Carbon::now()->subMonth()->format('F Y');
        $teamLoggerService = new TeamLoggerService();
        $teamLoggerData = $teamLoggerService->fetchByMonth($previousMonth, true);

        // Create CSV content
        $csvData = [];
        $csvData[] = ['Name', 'Amount P', 'Bank Column 1', 'Bank Column 2'];

        foreach ($users as $user) {
            $userEmail = strtolower(trim($user->email));
            $hoursLM = $teamLoggerData[$userEmail]['hours'] ?? 0;
            $salaryPP = $user->userSalary?->salary_pp ?? 0;
            $other = $user->userSalary?->other ?? 0;
            $amountP = (($hoursLM * $salaryPP) / 200) - $other;

            $csvData[] = [
                $user->name,
                round($amountP),
                $user->userSalary?->bank_1 ?? '',
                $user->userSalary?->bank_2 ?? '',
            ];
        }

        // Convert to CSV string
        $csv = '';
        foreach ($csvData as $row) {
            $csv .= '"' . implode('","', $row) . '"' . "\n";
        }

        // Generate filename with timestamp and user
        $timestamp = now()->format('Y-m-d_H-i-s');
        $username = auth()->user()->name;
        $filename = "team_export_{$timestamp}_{$username}.csv";

        // Save to export history
        Storage::put("export_history/{$filename}", $csv);

        // Return file for download
        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
