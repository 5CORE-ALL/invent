<?php

namespace App\Http\Controllers;

use App\Models\RrPortfolio;
use App\Models\RrPortfolioUser;
use App\Models\User;
use App\Services\UserRRPortfolioConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserRRPortfolioController extends Controller
{
    /**
     * Team-wide R&R checklist (tabulator page).
     *
     * Shows every active user with an at-a-glance view of:
     *  - whether they have any R&R portfolio assigned,
     *  - whether they have been marked as "fits" for it,
     *  - the latest portfolio document linked to them.
     */
    public function checklist()
    {
        $users = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'designation', 'role', 'avatar', 'updated_at']);

        $stats = [
            'total' => $users->count(),
            'assigned' => 0,
            'missing' => 0,
            'fits' => 0,
            'not_fits' => 0,
        ];

        $userIds = $users->pluck('id')->all();
        if (! empty($userIds)) {
            $assignedIds = RrPortfolioUser::query()
                ->whereIn('user_id', $userIds)
                ->pluck('user_id')
                ->unique();

            $fitsIds = RrPortfolioUser::query()
                ->whereIn('user_id', $userIds)
                ->where('fits', true)
                ->pluck('user_id')
                ->unique();

            $stats['assigned'] = $assignedIds->count();
            $stats['missing'] = $stats['total'] - $stats['assigned'];
            $stats['fits'] = $fitsIds->count();
            $stats['not_fits'] = $stats['assigned'] - $stats['fits'];
        } else {
            $stats['missing'] = $stats['total'];
        }

        $designations = $users
            ->pluck('designation')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('user.r-and-r-checklist', compact('stats', 'designations'));
    }

    /**
     * JSON feed for the R&R checklist tabulator.
     *
     * One row per active user, hydrated with the latest portfolio assignment
     * (if any). "fits" reflects the most recent assignment's flag.
     */
    public function checklistData(): JsonResponse
    {
        $users = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'designation', 'role', 'avatar', 'updated_at']);

        $assignments = RrPortfolioUser::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->with('portfolio:id,original_filename,source_format')
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('user_id');

        $placeholder = asset('images/users/add-image-placeholder.svg');

        $payload = $users->map(function (User $u) use ($assignments, $placeholder) {
            $latest = optional($assignments->get($u->id))->first();
            $portfolio = optional($latest)->portfolio;
            $hasPortfolio = (bool) $latest;

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'designation' => $u->designation ?? '',
                'role' => $u->role,
                'avatar_url' => ! empty($u->avatar)
                    ? asset('storage/'.$u->avatar)
                    : $placeholder,
                'has_portfolio' => $hasPortfolio,
                'fits' => $hasPortfolio ? (bool) $latest->fits : null,
                'assignment_id' => optional($latest)->id,
                'portfolio_id' => optional($portfolio)->id,
                'original_filename' => optional($portfolio)->original_filename,
                'source_format' => optional($portfolio)->source_format,
                'assigned_at' => optional(optional($latest)->updated_at)->toDateTimeString(),
                'assigned_at_human' => optional(optional($latest)->updated_at)->diffForHumans(),
                'user_updated_at' => optional($u->updated_at)->toDateTimeString(),
                'user_updated_at_human' => optional($u->updated_at)->diffForHumans(),
                'portfolio_url' => route('users.rr-portfolio.show', $u),
                // Designation-wise R&R checklist feed used by the magnifier modal.
                'checklist_url' => ! empty($u->designation)
                    ? route('performance.checklist.get', ['designationId' => $u->designation])
                    : null,
            ];
        });

        return response()->json($payload);
    }

    /**
     * R&R portfolio page(s) for this user — may include multiple shared documents.
     */
    public function show(User $user)
    {
        $assignments = RrPortfolioUser::query()
            ->where('user_id', $user->id)
            ->with('portfolio')
            ->orderByDesc('updated_at')
            ->get();

        $canUpload = auth()->check() && auth()->user()->email === 'president@5core.com';

        $teamUsers = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('pages.user-rr-portfolio', compact('user', 'assignments', 'canUpload', 'teamUsers'));
    }

    /**
     * Upload CSV / TXT / HTML / Excel; convert to HTML and assign to one or many users.
     */
    public function upload(Request $request, User $user, UserRRPortfolioConverter $converter)
    {
        if (auth()->user()->email !== 'president@5core.com') {
            abort(403, 'Only the administrator can upload portfolio files.');
        }

        $request->validate([
            'portfolio_file' => 'required|file|max:10240|mimes:csv,txt,htm,html,xlsx,xls',
            'assign_scope' => 'required|in:single,multiple',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ], [
            'portfolio_file.required' => 'Please choose a file.',
            'portfolio_file.mimes' => 'Allowed types: CSV, TXT, HTML, XLS, XLSX.',
        ]);

        $assignScope = $request->input('assign_scope');
        if ($assignScope === 'multiple') {
            $userIds = array_values(array_unique(array_map('intval', $request->input('user_ids', []))));
            if (count($userIds) === 0) {
                return redirect()
                    ->route('users.rr-portfolio.show', $user)
                    ->withErrors(['user_ids' => 'Select at least one team member for “Selected users”.']);
            }
        } else {
            $userIds = [(int) $user->id];
        }

        $activeCount = User::query()->where('is_active', true)->whereIn('id', $userIds)->count();
        if ($activeCount !== count($userIds)) {
            return redirect()
                ->route('users.rr-portfolio.show', $user)
                ->withErrors(['user_ids' => 'Only active team members can be selected.']);
        }

        try {
            $html = $converter->convert($request->file('portfolio_file'));
        } catch (\Throwable $e) {
            return redirect()
                ->route('users.rr-portfolio.show', $user)
                ->withErrors(['portfolio_file' => 'Could not convert file: '.$e->getMessage()]);
        }

        $file = $request->file('portfolio_file');

        DB::transaction(function () use ($html, $file, $userIds) {
            $portfolio = RrPortfolio::create([
                'html_content' => $html,
                'original_filename' => $file->getClientOriginalName(),
                'source_format' => $file->getClientOriginalExtension(),
            ]);

            foreach ($userIds as $uid) {
                RrPortfolioUser::create([
                    'rr_portfolio_id' => $portfolio->id,
                    'user_id' => $uid,
                    'fits' => false,
                ]);
            }
        });

        $msg = count($userIds) > 1
            ? 'File converted and linked to '.count($userIds).' users.'
            : 'File converted to HTML and saved on this portfolio page.';

        return redirect()
            ->route('users.rr-portfolio.show', $user)
            ->with('success', $msg);
    }

    /**
     * Update whether this user fits the R&R document (president only).
     */
    public function updateFits(Request $request, User $user, RrPortfolioUser $assignment)
    {
        if (auth()->user()->email !== 'president@5core.com') {
            abort(403);
        }

        if ((int) $assignment->user_id !== (int) $user->id) {
            abort(404);
        }

        $assignment->update([
            'fits' => $request->boolean('fits'),
        ]);

        return redirect()
            ->route('users.rr-portfolio.show', $user)
            ->with('success', '“Fits this R&R” updated.');
    }
}
