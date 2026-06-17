<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\RrPortfolioUser;
use App\Models\User;
use App\Models\UserDoc;
use App\Services\TeamLoggerService;
use App\Support\TeamManagementAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class UserController extends Controller
{
    public function index()
    {
        if (! TeamManagementAccess::canView()) {
            abort(403, 'Not Authorised');
        }

        // Load all users (active + inactive) for the merged table; the Active/Inactive
        // toggle filters them client-side so there's no separate inactive page.
        // select() must run before withCount(): a later select() replaces columns and drops the count subquery.
        $allUsers = User::query()
            ->select('id', 'name', 'phone', 'email', 'designation', 'date_of_joining', 'avatar', 'is_active', 'deactivated_at', 'show_in_salary', 'resume_path', 'resume_original_name')
            ->with(['userRR', 'userSalary', 'docs'])
            ->withCount('rrPortfolioAssignments')
            ->orderBy('name')
            ->get();

        // Active subset drives the salary/header totals so they stay meaningful.
        $users = $allUsers->where('is_active', true)->values();

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
        
        // First, fetch from API to get all current data
        $teamLoggerService = new TeamLoggerService();
        $teamLoggerData = $teamLoggerService->fetchByMonth($previousMonth, true);
        
        // Then, get manually edited hours from database and override API data
        $teamLoggerHours = \App\Models\TeamLoggerHours::where('month', $previousMonth)->get();
        
        // Override with database values for manually edited hours
        foreach ($teamLoggerHours as $record) {
            $email = strtolower($record->employee_email);
            if (isset($teamLoggerData[$email])) {
                // Keep API data but override productive_hours with database value
                $teamLoggerData[$email]['hours'] = $record->productive_hours;
            } else {
                // If not in API data, add from database
                $teamLoggerData[$email] = [
                    'hours' => $record->productive_hours,
                    'total_hours' => $record->total_hours ?? 0,
                    'idle_hours' => $record->idle_hours ?? 0,
                    'active_hours' => $record->active_hours ?? 0,
                ];
            }
        }

        // Fetch TeamLogger data for the CURRENT month (used by the Hours column on the page)
        $currentMonth = Carbon::now()->format('F Y');
        $currentMonthData = $teamLoggerService->fetchByMonth($currentMonth, true);

        $currentMonthHours = \App\Models\TeamLoggerHours::where('month', $currentMonth)->get();
        foreach ($currentMonthHours as $record) {
            $email = strtolower($record->employee_email);
            if (isset($currentMonthData[$email])) {
                $currentMonthData[$email]['hours'] = $record->productive_hours;
            } else {
                $currentMonthData[$email] = [
                    'hours' => $record->productive_hours,
                    'total_hours' => $record->total_hours ?? 0,
                    'idle_hours' => $record->idle_hours ?? 0,
                    'active_hours' => $record->active_hours ?? 0,
                ];
            }
        }

        // Email mapping: Map user email to TeamLogger email if different
        $emailMapping = [
            'adexec1@5core.com' => 'support@prolightsounds.com',
        ];

        // Filter users for salary tab (only show users with show_in_salary = true)
        $salaryUsers = $users->filter(function($user) {
            return $user->show_in_salary !== false;
        });

        $canEdit = TeamManagementAccess::canEdit();
        $canViewSalary = TeamManagementAccess::canViewSalary();
        $canViewResume = TeamManagementAccess::canViewResume();
        $canEditResume = TeamManagementAccess::canEditResume();

        return view('pages.add-user', compact(
            'users',
            'allUsers',
            'salaryUsers',
            'canEdit',
            'canViewSalary',
            'canViewResume',
            'canEditResume',
            'totalSalaryPP',
            'totalIncrement',
            'teamLoggerData',
            'currentMonthData',
            'currentMonth',
            'previousMonth',
            'emailMapping'
        ));
    }

    /**
     * Update a user's bank details. Update-only: blank fields are left untouched so
     * existing values can never be deleted, only changed.
     */
    public function updateBank(Request $request, User $user)
    {
        if (! TeamManagementAccess::canViewSalary()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit bank details.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'bank_1' => 'nullable|string|max:65535',
            'bank_2' => 'nullable|string|max:65535',
            'upi_id' => 'nullable|string|max:65535',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Only write fields that were sent with a non-empty value (never blank out existing data).
        $bankData = [];
        foreach (['bank_1', 'bank_2', 'upi_id'] as $field) {
            $value = trim((string) $request->input($field, ''));
            if ($value !== '') {
                $bankData[$field] = $value;
            }
        }

        if (empty($bankData)) {
            return response()->json([
                'success' => false,
                'message' => 'Nothing to update — enter at least one value.',
            ], 422);
        }

        $user->userSalary()->updateOrCreate(['user_id' => $user->id], $bankData);
        $user->load('userSalary');

        return response()->json([
            'success' => true,
            'message' => 'Bank details updated.',
            'bank_1' => $user->userSalary->bank_1 ?? '',
            'bank_2' => $user->userSalary->bank_2 ?? '',
            'upi_id' => $user->userSalary->upi_id ?? '',
        ]);
    }

    /**
     * Stream a user's resume file (president/hr only).
     */
    public function showResume(User $user)
    {
        if (! TeamManagementAccess::canViewResume()) {
            abort(403, 'You do not have permission to view resumes.');
        }

        if (! $user->resume_path || ! Storage::disk('local')->exists($user->resume_path)) {
            abort(404, 'No resume on file for this user.');
        }

        $downloadName = $user->resume_original_name
            ?: ('resume-' . \Illuminate\Support\Str::slug($user->name) . '.' . pathinfo($user->resume_path, PATHINFO_EXTENSION));

        return Storage::disk('local')->download($user->resume_path, $downloadName);
    }

    /**
     * Upload or replace a user's resume file (hr only).
     */
    public function uploadResume(Request $request, User $user)
    {
        if (! TeamManagementAccess::canEditResume()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to manage resumes.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'resume' => 'required|file|mimes:pdf,doc,docx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Remove the previous file (if any) before storing the new one.
        if ($user->resume_path && Storage::disk('local')->exists($user->resume_path)) {
            Storage::disk('local')->delete($user->resume_path);
        }

        $file = $request->file('resume');
        $originalName = $file->getClientOriginalName();
        $storedName = time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs("resumes/{$user->id}", $storedName, 'local');

        $user->resume_path = $path;
        $user->resume_original_name = $originalName;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Resume uploaded successfully.',
            'has_resume' => true,
            'resume_name' => $originalName,
            'resume_url' => route('users.resume.show', $user),
        ]);
    }

    /**
     * Delete a user's resume file (hr only).
     */
    public function deleteResume(User $user)
    {
        if (! TeamManagementAccess::canEditResume()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to manage resumes.',
            ], 403);
        }

        if ($user->resume_path && Storage::disk('local')->exists($user->resume_path)) {
            Storage::disk('local')->delete($user->resume_path);
        }

        $user->resume_path = null;
        $user->resume_original_name = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Resume removed.',
            'has_resume' => false,
        ]);
    }

    /**
     * Normalise a UserDoc into the shape used by the UI / JSON responses.
     */
    private function mapDoc(UserDoc $doc): array
    {
        return [
            'id' => $doc->id,
            'type' => $doc->type,
            'label' => $doc->label,
            'name' => $doc->type === 'file'
                ? ($doc->label ?: ($doc->original_name ?: 'File'))
                : ($doc->label ?: $doc->url),
            'url' => $doc->type === 'link'
                ? $doc->url
                : route('users.docs.download', [$doc->user_id, $doc->id]),
        ];
    }

    private function docsPayload(User $user): array
    {
        $user->load('docs');

        return [
            'success' => true,
            'docs' => $user->docs->map(fn (UserDoc $d) => $this->mapDoc($d))->values(),
            'has_docs' => $user->docs->isNotEmpty(),
        ];
    }

    /**
     * List a user's docs (president/hr only).
     */
    public function listDocs(User $user)
    {
        if (! TeamManagementAccess::canViewResume()) {
            return response()->json(['success' => false, 'message' => 'Not Authorised'], 403);
        }

        return response()->json($this->docsPayload($user));
    }

    /**
     * Add a link or upload one or more files to a user's docs (hr only).
     */
    public function storeDoc(Request $request, User $user)
    {
        if (! TeamManagementAccess::canEditResume()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to manage docs.',
            ], 403);
        }

        // Repeatable rows: each row may carry a name, a file, and/or a link.
        if (is_array($request->input('rows')) || is_array($request->file('rows'))) {
            $rowsInput = $request->input('rows', []);
            $created = 0;

            foreach (array_keys($rowsInput + ($request->file('rows') ?? [])) as $i) {
                $name = trim((string) ($rowsInput[$i]['name'] ?? ''));
                $link = trim((string) ($rowsInput[$i]['link'] ?? ''));
                $file = $request->file("rows.$i.file");

                if ($file) {
                    $validator = Validator::make(['file' => $file], [
                        'file' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,gif,zip,txt,csv',
                    ]);
                    if ($validator->fails()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Validation failed',
                            'errors' => $validator->errors(),
                        ], 422);
                    }

                    $storedName = time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs("docs/{$user->id}", $storedName, 'local');

                    $user->docs()->create([
                        'type' => 'file',
                        'label' => $name !== '' ? $name : null,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                    ]);
                    $created++;
                }

                if ($link !== '') {
                    $validator = Validator::make(['url' => $link], ['url' => 'url|max:2048']);
                    if ($validator->fails()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'One of the links is not a valid URL.',
                            'errors' => $validator->errors(),
                        ], 422);
                    }

                    $user->docs()->create([
                        'type' => 'link',
                        'label' => $name !== '' ? $name : null,
                        'url' => $link,
                    ]);
                    $created++;
                }
            }

            if ($created === 0) {
                return response()->json(['success' => false, 'message' => 'Add at least one file or link.'], 422);
            }

            return response()->json(array_merge($this->docsPayload($user), ['message' => 'Saved.']));
        }

        $type = $request->input('type');

        if ($type === 'link') {
            $validator = Validator::make($request->all(), [
                'url' => 'required|url|max:2048',
                'label' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user->docs()->create([
                'type' => 'link',
                'label' => $request->input('label'),
                'url' => $request->input('url'),
            ]);
        } elseif ($type === 'file') {
            $validator = Validator::make($request->all(), [
                'files' => 'required|array|min:1',
                'files.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,gif,zip,txt,csv',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            foreach ($request->file('files') as $file) {
                $storedName = time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs("docs/{$user->id}", $storedName, 'local');

                $user->docs()->create([
                    'type' => 'file',
                    'label' => null,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid document type.'], 422);
        }

        return response()->json(array_merge(
            $this->docsPayload($user),
            ['message' => 'Saved.']
        ));
    }

    /**
     * Download a doc file (president/hr only).
     */
    public function downloadDoc(User $user, UserDoc $doc)
    {
        if (! TeamManagementAccess::canViewResume()) {
            abort(403, 'Not Authorised');
        }

        if ($doc->user_id !== $user->id || $doc->type !== 'file' || ! $doc->path || ! Storage::disk('local')->exists($doc->path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->download($doc->path, $doc->original_name ?: 'document');
    }

    /**
     * Delete a doc (hr only).
     */
    public function deleteDoc(User $user, UserDoc $doc)
    {
        if (! TeamManagementAccess::canEditResume()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to manage docs.',
            ], 403);
        }

        if ($doc->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
        }

        if ($doc->type === 'file' && $doc->path && Storage::disk('local')->exists($doc->path)) {
            Storage::disk('local')->delete($doc->path);
        }

        $doc->delete();

        return response()->json(array_merge(
            $this->docsPayload($user),
            ['message' => 'Document removed.']
        ));
    }

    public function update(Request $request, User $user)
    {
        if (! TeamManagementAccess::canEdit()) {
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
            'date_of_joining' => 'nullable|date',
            'rr_role' => 'nullable|string|max:65535',
            'training' => 'nullable|string|max:65535',
            'resources' => 'nullable|string|max:65535',
            'salary_pp' => 'nullable|numeric|min:0',
            'increment' => 'nullable|numeric|min:0',
            'hours_lm' => 'nullable|numeric|min:0',
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
        // Only update the joining date when the field is part of the submission so
        // modals that omit it (e.g. the Users-tab quick edit) never clear it.
        if ($request->has('date_of_joining')) {
            $user->date_of_joining = $request->input('date_of_joining') ?: null;
        }
        $user->save();

        // Only touch R&R fields that were actually submitted, so editing core user
        // info (the modal omits these) never wipes existing R&R/training/resources.
        $rrMap = ['rr_role' => 'role', 'training' => 'training', 'resources' => 'resources'];
        $rrData = [];
        foreach ($rrMap as $requestKey => $column) {
            if ($request->has($requestKey)) {
                $rrData[$column] = $request->input($requestKey);
            }
        }
        if (! empty($rrData)) {
            $user->userRR()->updateOrCreate(
                ['user_id' => $user->id],
                $rrData
            );
        }
        $user->load('userRR');

        // Only touch salary fields that were actually submitted, so editing user info
        // (e.g. from the Users tab modal, which omits salary) never wipes salary data.
        $salaryData = [];
        foreach (['salary_pp', 'increment', 'other', 'adv_inc_other', 'bank_1', 'bank_2', 'upi_id'] as $salaryField) {
            if ($request->has($salaryField)) {
                $salaryData[$salaryField] = $request->input($salaryField);
            }
        }
        if (! empty($salaryData)) {
            $user->userSalary()->updateOrCreate(
                ['user_id' => $user->id],
                $salaryData
            );
        }
        $user->load('userSalary');

        // Update TeamLogger hours if provided
        if ($request->has('hours_lm') && $request->input('hours_lm') !== '') {
            $previousMonthDate = Carbon::now()->subMonth();
            $previousMonth = $previousMonthDate->format('F Y');
            $startDate = $previousMonthDate->startOfMonth()->format('Y-m-d');
            $endDate = $previousMonthDate->endOfMonth()->format('Y-m-d');
            
            $userEmail = strtolower(trim($user->email));
            
            // Email mapping
            $emailMapping = [
                'adexec1@5core.com' => 'support@prolightsounds.com',
            ];
            $teamLoggerEmail = $emailMapping[$userEmail] ?? $userEmail;
            
            \App\Models\TeamLoggerHours::updateOrCreate(
                [
                    'employee_email' => $teamLoggerEmail,
                    'month' => $previousMonth,
                ],
                [
                    'productive_hours' => $request->input('hours_lm'),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'designation' => $user->designation,
                'date_of_joining' => $user->date_of_joining?->format('Y-m-d'),
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
        if (! TeamManagementAccess::canEdit()) {
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
        if (! TeamManagementAccess::canEdit()) {
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
        if (! TeamManagementAccess::canManageSalary()) {
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
        if (! TeamManagementAccess::canManageSalary()) {
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

    public function exportData()
    {
        if (! TeamManagementAccess::canViewSalary()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to export data.'
            ], 403);
        }

        // Get active users with salary and TeamLogger data (only those shown in salary tab)
        $users = User::query()
            ->where('is_active', true)
            ->where('show_in_salary', true)
            ->select('id', 'name', 'email', 'show_in_salary')
            ->with(['userSalary'])
            ->orderBy('name')
            ->get();

        // Fetch TeamLogger data for previous month
        $previousMonth = Carbon::now()->subMonth()->format('F Y');
        
        // First, fetch from API to get all current data
        $teamLoggerService = new TeamLoggerService();
        $teamLoggerData = $teamLoggerService->fetchByMonth($previousMonth, true);
        
        // Then, get manually edited hours from database and override API data
        $teamLoggerHours = \App\Models\TeamLoggerHours::where('month', $previousMonth)->get();
        
        // Override with database values for manually edited hours
        foreach ($teamLoggerHours as $record) {
            $email = strtolower($record->employee_email);
            if (isset($teamLoggerData[$email])) {
                // Keep API data but override productive_hours with database value
                $teamLoggerData[$email]['hours'] = $record->productive_hours;
            } else {
                // If not in API data, add from database
                $teamLoggerData[$email] = [
                    'hours' => $record->productive_hours,
                    'total_hours' => $record->total_hours ?? 0,
                    'idle_hours' => $record->idle_hours ?? 0,
                    'active_hours' => $record->active_hours ?? 0,
                ];
            }
        }

        // Email mapping: Map user email to TeamLogger email if different
        $emailMapping = [
            'adexec1@5core.com' => 'support@prolightsounds.com',
        ];

        // Create CSV content
        $csvData = [];
        $csvData[] = ['Name', 'Hours LM', 'Amount P', 'B1', 'B2', 'UPI'];

        foreach ($users as $user) {
            $userEmail = strtolower(trim($user->email));
            $teamLoggerEmail = $emailMapping[$userEmail] ?? $userEmail;
            $hoursLM = $teamLoggerData[$teamLoggerEmail]['hours'] ?? 0;
            $salaryPP = $user->userSalary?->salary_pp ?? 0;
            $increment = $user->userSalary?->increment ?? 0;
            $salaryLM = $salaryPP + $increment;
            $other = $user->userSalary?->other ?? 0;
            $advIncOther = $user->userSalary?->adv_inc_other ?? 0;
            $amountP = (($hoursLM * $salaryLM) / 200) + $other - $advIncOther;
            $amountPRounded = round($amountP / 100) * 100;

            $csvData[] = [
                $user->name,
                $hoursLM . 'h',
                $amountPRounded,
                $user->userSalary?->bank_1 ?? '',
                $user->userSalary?->bank_2 ?? '',
                $user->userSalary?->upi_id ?? '',
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

    public function toggleSalaryVisibility(Request $request, User $user)
    {
        if (! TeamManagementAccess::canManageSalary()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.'
            ], 403);
        }

        // Toggle the show_in_salary status
        $user->show_in_salary = !$user->show_in_salary;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => $user->show_in_salary ? 'User will now appear in salary tab' : 'User hidden from salary tab',
            'show_in_salary' => $user->show_in_salary
        ]);
    }
}
