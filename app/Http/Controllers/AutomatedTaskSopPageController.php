<?php

namespace App\Http\Controllers;

use App\Models\AutomateTaskSopPage;
use App\Models\User;
use App\Support\AutomatedTaskSopPageAi;
use App\Support\AutomatedTaskSopPageBuilder;
use App\Support\AutomatedTaskSopSourceReader;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AutomatedTaskSopPageController extends Controller
{
    public function show(int $id)
    {
        $task = DB::table('automate_tasks')->where('id', $id)->first();
        if (! $task) {
            abort(404, 'Automated task not found.');
        }

        $page = AutomateTaskSopPage::query()->where('automate_task_id', $id)->first();
        $sopLink = trim((string) ($task->link3 ?? ''));
        if (! $page && $sopLink === '') {
            abort(404, 'SOP page has not been created yet.');
        }

        if (! $page) {
            return view('tasks.sop-page-generating', [
                'task' => $task,
                'ensureUrl' => route('tasks.automatedSopPage.ensure', $id),
                'showUrl' => route('tasks.automatedSopPage.show', $id),
            ]);
        }

        $user = Auth::user();

        return view('tasks.sop-page', [
            'task' => $task,
            'page' => $page,
            'canEdit' => $this->isAssignor($user, $task),
            'canUseAiBox' => $this->canUseAiBox($user, $task),
        ]);
    }

    public function edit(int $id)
    {
        $task = DB::table('automate_tasks')->where('id', $id)->first();
        if (! $task) {
            abort(404, 'Automated task not found.');
        }

        $user = Auth::user();
        if (! $this->isAssignor($user, $task)) {
            abort(403, 'Only the assignor can edit this SOP page.');
        }

        $page = AutomateTaskSopPage::query()->where('automate_task_id', $id)->first();
        if (! $page) {
            $page = $this->buildPage($task, $user, false);
        }

        return view('tasks.sop-page-edit', [
            'task' => $task,
            'page' => $page,
        ]);
    }

    public function ensure(int $id)
    {
        $task = DB::table('automate_tasks')->where('id', $id)->first();
        if (! $task) {
            return response()->json(['message' => 'Automated task not found.'], 404);
        }

        $sopLink = trim((string) ($task->link3 ?? ''));
        if ($sopLink === '') {
            return response()->json(['message' => 'This task has no SOP link.'], 422);
        }

        @set_time_limit(180);
        try {
            $page = $this->buildPage($task, Auth::user(), false);
        } catch (\Throwable $e) {
            \Log::error('SOP page ensure failed', ['id' => $id, 'msg' => $e->getMessage()]);

            return response()->json([
                'message' => 'Could not build the SOP page: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'SOP page ready.',
            'has_sop_page' => true,
            'sop_page_url' => route('tasks.automatedSopPage.show', $id),
            'title' => $page->title,
        ]);
    }

    public function createFromFile(Request $request, int $id)
    {
        $task = DB::table('automate_tasks')->where('id', $id)->first();
        if (! $task) {
            return response()->json(['message' => 'Automated task not found.'], 404);
        }

        $user = Auth::user();
        $sopLink = trim((string) ($task->link3 ?? ''));
        if ($sopLink === '') {
            return response()->json(['message' => 'Add an SOP link or file first, then create the page.'], 422);
        }

        @set_time_limit(180);
        try {
            $this->buildPage($task, $user, true);
        } catch (\Throwable $e) {
            \Log::error('SOP page create failed', ['id' => $id, 'msg' => $e->getMessage()]);

            return response()->json([
                'message' => 'Could not build the SOP page: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'SOP page created from the SOP link.',
            'has_sop_page' => true,
            'sop_page_url' => route('tasks.automatedSopPage.show', $id),
            'edit_url' => route('tasks.automatedSopPage.edit', $id),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $task = DB::table('automate_tasks')->where('id', $id)->first();
        if (! $task) {
            abort(404, 'Automated task not found.');
        }

        $user = Auth::user();
        if (! $this->isAssignor($user, $task)) {
            abort(403, 'Only the assignor can edit this SOP page.');
        }

        $page = AutomateTaskSopPage::query()->where('automate_task_id', $id)->firstOrFail();

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'body' => 'required|string',
        ]);

        $page->title = $validated['title'] ?? $page->title;
        $page->body = AutomatedTaskSopPageBuilder::sanitizeHtml($validated['body']);
        $page->updated_by = $user->id;
        $page->save();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'SOP page saved.',
                'sop_page_url' => route('tasks.automatedSopPage.show', $id),
            ]);
        }

        return redirect()
            ->route('tasks.automatedSopPage.show', $id)
            ->with('success', 'SOP page saved.');
    }

    public function reviseWithAi(Request $request, int $id)
    {
        $task = DB::table('automate_tasks')->where('id', $id)->first();
        if (! $task) {
            return response()->json(['message' => 'Automated task not found.'], 404);
        }

        $user = Auth::user();
        if (! $this->canUseAiBox($user, $task)) {
            return response()->json(['message' => 'Only the assignor, president@5core.com, or a Director can use this box.'], 403);
        }

        $page = AutomateTaskSopPage::query()->where('automate_task_id', $id)->first();
        if (! $page) {
            return response()->json(['message' => 'SOP page has not been created yet.'], 404);
        }

        $validated = $request->validate([
            'instruction' => 'required|string|min:3|max:4000',
        ]);

        @set_time_limit(180);
        try {
            $revised = AutomatedTaskSopPageAi::revise(
                $task,
                (string) $page->body,
                $validated['instruction'],
                (string) ($page->title ?? '')
            );
        } catch (\Throwable $e) {
            \Log::error('SOP page AI revise failed', ['id' => $id, 'msg' => $e->getMessage()]);

            return response()->json([
                'message' => 'Could not update the SOP page: '.$e->getMessage(),
            ], 500);
        }

        $page->title = $revised['title'];
        $page->body = $revised['html'];
        $page->updated_by = $user?->id;
        $page->save();

        return response()->json([
            'message' => 'SOP page updated.',
            'title' => $page->title,
            'body' => $page->body,
        ]);
    }

    private function buildPage(object $task, ?User $user, bool $overwrite): AutomateTaskSopPage
    {
        $id = (int) $task->id;
        $existing = AutomateTaskSopPage::query()->where('automate_task_id', $id)->first();

        $sopLink = trim((string) ($task->link3 ?? ''));
        $material = AutomatedTaskSopPageBuilder::extractSourceMaterial($sopLink);
        if ($existing && ! $overwrite) {
            $body = (string) $existing->body;
            $hasOk = str_contains($body, 'data-sop-source="ok"');
            $hasTried = str_contains($body, 'data-sop-source=');
            if ($hasOk || ($hasTried && empty($material['fetched']))) {
                return $existing;
            }
        }

        $built = AutomatedTaskSopPageAi::elaborate($task, $material);
        $html = AutomatedTaskSopPageAi::attachVisuals($task, $built['html'], $built['scenes'] ?? []);
        $html .= AutomatedTaskSopSourceReader::sourceSection($material);

        $page = $existing ?: new AutomateTaskSopPage();
        $page->automate_task_id = $id;
        if (! $page->exists) {
            $page->created_by = $user?->id;
        }
        $page->title = $built['title'];
        $page->body = $html;
        $page->source_link = $sopLink;
        $page->updated_by = $user?->id;

        try {
            $page->save();
        } catch (QueryException $e) {
            $again = AutomateTaskSopPage::query()->where('automate_task_id', $id)->first();
            if ($again && ! $overwrite) {
                return $again;
            }
            throw $e;
        }

        return $page;
    }

    private function isAssignor(?User $user, object $task): bool
    {
        if (! $user) {
            return false;
        }

        $assignor = strtolower(trim((string) ($task->assignor ?? '')));
        $email = strtolower(trim((string) ($user->email ?? '')));

        return $assignor !== '' && $email !== '' && $assignor === $email;
    }

    private function isPresident(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return strtolower(trim((string) ($user->email ?? ''))) === 'president@5core.com';
    }

    private function isDirector(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $org = strtolower(trim((string) ($user->org_level ?? '')));
        if ($org === 'director') {
            return true;
        }

        return strtolower(trim((string) ($user->role ?? ''))) === 'director';
    }

    private function canUseAiBox(?User $user, object $task): bool
    {
        return $this->isAssignor($user, $task)
            || $this->isPresident($user)
            || $this->isDirector($user);
    }
}
