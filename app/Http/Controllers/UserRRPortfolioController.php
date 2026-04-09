<?php

namespace App\Http\Controllers;

use App\Models\RrPortfolio;
use App\Models\RrPortfolioUser;
use App\Models\User;
use App\Services\UserRRPortfolioConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserRRPortfolioController extends Controller
{
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
