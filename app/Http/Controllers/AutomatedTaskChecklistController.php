<?php

namespace App\Http\Controllers;

use App\Models\AutomateTaskChecklistForm;
use App\Models\AutomateTaskChecklistSubmission;
use App\Models\User;
use App\Support\AutomatedTaskChecklistIds;
use App\Support\SuperAdminAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutomatedTaskChecklistController extends Controller
{
    /**
     * Senior / Director (and admin) may create or edit checklist form definitions.
     * Checks users.role first; also honors org_level (director/mgr) used in this app.
     */
    public static function canManageChecklist(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (SuperAdminAccess::isTaskAdmin($user)) {
            return true;
        }

        $role = strtolower(trim((string) ($user->role ?? '')));
        if (in_array($role, ['senior', 'director', 'admin'], true)) {
            return true;
        }

        $org = strtolower(trim((string) ($user->org_level ?? '')));
        if (in_array($org, ['director', 'mgr'], true)) {
            return true;
        }

        return false;
    }

    public function show(int $automateTaskId)
    {
        $task = DB::table('automate_tasks')->where('id', $automateTaskId)->first();
        if (! $task) {
            return response()->json(['message' => 'Automated task not found.'], 404);
        }

        $form = AutomateTaskChecklistForm::query()
            ->where('automate_task_id', $automateTaskId)
            ->first();

        $user = Auth::user();
        $sopLink = trim((string) ($task->link3 ?? ''));
        if ($sopLink !== '' && ! preg_match('#^https?://#i', $sopLink)) {
            $sopLink = '';
        }

        return response()->json([
            'automate_task_id' => $automateTaskId,
            'task_title' => $task->title ?? '',
            'sop_link' => $sopLink !== '' ? $sopLink : null,
            'can_manage' => self::canManageChecklist($user),
            'form' => $form ? AutomatedTaskChecklistIds::formPayload($form) : null,
            'submission_count' => $form
                ? AutomateTaskChecklistSubmission::query()->where('form_id', $form->id)->count()
                : 0,
        ]);
    }

    public function save(Request $request, int $automateTaskId)
    {
        $user = Auth::user();
        if (! self::canManageChecklist($user)) {
            return response()->json(['message' => 'Only Senior and Director roles can create or edit checklist forms.'], 403);
        }

        $task = DB::table('automate_tasks')->where('id', $automateTaskId)->first();
        if (! $task) {
            return response()->json(['message' => 'Automated task not found.'], 404);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'questions' => 'required|array|min:1',
            'questions.*.id' => 'nullable|string|max:64',
            'questions.*.type' => 'required|in:checkbox,text',
            'questions.*.label' => 'required|string|max:1000',
            'questions.*.required' => 'nullable|boolean',
        ]);

        $questions = [];
        foreach ($validated['questions'] as $q) {
            $questions[] = [
                'id' => ! empty($q['id']) ? (string) $q['id'] : 'q_'.Str::lower(Str::random(10)),
                'type' => $q['type'],
                'label' => trim($q['label']),
                'required' => (bool) ($q['required'] ?? false),
            ];
        }

        $title = $validated['title'] ?? ($task->title ? ($task->title.' Checklist') : 'Checklist Form');

        $form = AutomateTaskChecklistForm::query()->firstOrNew(['automate_task_id' => $automateTaskId]);
        if (! $form->exists) {
            $form->created_by = $user->id;
        }
        $form->title = $title;
        $form->questions = $questions;
        $form->updated_by = $user->id;
        $form->save();

        $clId = AutomatedTaskChecklistIds::ensureOnForm($form);
        AutomatedTaskChecklistIds::applyToTemplateAndFiredTasks((int) $automateTaskId, $clId);

        return response()->json([
            'message' => 'Checklist form saved.',
            'form' => AutomatedTaskChecklistIds::formPayload($form->fresh() ?: $form),
        ]);
    }

    public function submit(Request $request, int $automateTaskId)
    {
        $form = AutomateTaskChecklistForm::query()
            ->where('automate_task_id', $automateTaskId)
            ->first();

        if (! $form) {
            return response()->json(['message' => 'No checklist form is attached to this task.'], 422);
        }

        $questions = $form->questions ?? [];
        if (! is_array($questions) || count($questions) === 0) {
            return response()->json(['message' => 'Checklist form has no questions.'], 422);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
        ]);

        $answersIn = $validated['answers'];
        $normalized = [];

        foreach ($questions as $q) {
            $qid = (string) ($q['id'] ?? '');
            if ($qid === '') {
                continue;
            }
            $type = $q['type'] ?? 'text';
            $required = (bool) ($q['required'] ?? false);
            $raw = $answersIn[$qid] ?? null;

            if ($type === 'checkbox') {
                // Supports {answer, actions[], corrective_action} or legacy boolean/string action.
                $isYes = null;
                $actions = [];
                $corrective = '';

                if (is_array($raw)) {
                    if (array_key_exists('answer', $raw)) {
                        $parsed = filter_var($raw['answer'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        $isYes = $parsed === null ? (bool) $raw['answer'] : $parsed;
                    }
                    if (isset($raw['actions']) && is_array($raw['actions'])) {
                        foreach ($raw['actions'] as $act) {
                            $act = trim((string) $act);
                            if ($act !== '') {
                                $actions[] = $act;
                            }
                        }
                    } elseif (! empty($raw['action'])) {
                        $actions[] = trim((string) $raw['action']);
                    }
                    $corrective = trim((string) ($raw['corrective_action'] ?? ''));
                } elseif ($raw === 'yes' || $raw === 'no') {
                    $isYes = $raw === 'yes';
                } elseif ($raw !== null) {
                    $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    $isYes = $parsed === null ? (bool) $raw : $parsed;
                }

                if ($isYes === null) {
                    return response()->json(['message' => 'Select Yes or No for: '.($q['label'] ?? $qid)], 422);
                }

                if (! $isYes && count($actions) === 0 && $corrective === '') {
                    return response()->json([
                        'message' => 'For "'.($q['label'] ?? $qid).'", enter Action or Corrective action (minimum one).',
                    ], 422);
                }

                $normalized[$qid] = [
                    'answer' => (bool) $isYes,
                    'actions' => $isYes ? [] : array_values($actions),
                    'action' => $isYes ? '' : ($actions[0] ?? ''),
                    'corrective_action' => $isYes ? '' : $corrective,
                ];
            } else {
                $val = is_string($raw) ? trim($raw) : (string) ($raw ?? '');
                if ($required && $val === '') {
                    return response()->json(['message' => 'Please complete required field: '.($q['label'] ?? $qid)], 422);
                }
                $normalized[$qid] = $val;
            }
        }

        $submission = AutomateTaskChecklistSubmission::create([
            'form_id' => $form->id,
            'automate_task_id' => $automateTaskId,
            'submitted_by' => Auth::id(),
            'answers' => $normalized,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Checklist submitted.',
            'submission_id' => $submission->id,
            'cl_id' => AutomatedTaskChecklistIds::ensureOnForm($form),
            'submission_count' => AutomateTaskChecklistSubmission::query()
                ->where('form_id', $form->id)
                ->count(),
        ]);
    }

    public function downloadTemplate(int $automateTaskId)
    {
        $task = DB::table('automate_tasks')->where('id', $automateTaskId)->first();
        if (! $task) {
            return response()->json(['message' => 'Automated task not found.'], 404);
        }

        $form = AutomateTaskChecklistForm::query()
            ->where('automate_task_id', $automateTaskId)
            ->first();

        $questions = is_array($form?->questions) ? $form->questions : [];
        if ($questions === []) {
            $questions = [
                ['type' => 'checkbox', 'label' => 'Example yes/no question', 'required' => false],
                ['type' => 'text', 'label' => 'Example text question', 'required' => false],
            ];
        }

        $safeTitle = Str::slug((string) ($form?->title ?? $task->title ?? 'checklist'), '-');
        $filename = 'checklist-template-'.($safeTitle !== '' ? $safeTitle : $automateTaskId).'.csv';

        return response()->streamDownload(function () use ($questions) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['type', 'label', 'required']);
            foreach ($questions as $q) {
                $type = strtolower((string) ($q['type'] ?? 'text'));
                if ($type !== 'checkbox' && $type !== 'text') {
                    $type = 'text';
                }
                fputcsv($out, [
                    $type,
                    (string) ($q['label'] ?? ''),
                    ! empty($q['required']) ? '1' : '0',
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function uploadTemplate(Request $request, int $automateTaskId)
    {
        $user = Auth::user();
        if (! self::canManageChecklist($user)) {
            return response()->json(['message' => 'Only Senior and Director roles can upload checklist templates.'], 403);
        }

        $task = DB::table('automate_tasks')->where('id', $automateTaskId)->first();
        if (! $task) {
            return response()->json(['message' => 'Automated task not found.'], 404);
        }

        $request->validate([
            'file' => 'required|file|max:2048',
        ]);

        $ext = strtolower((string) $request->file('file')->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt'], true)) {
            return response()->json(['message' => 'Upload a CSV template (type,label,required).'], 422);
        }

        $path = $request->file('file')->getRealPath();
        $fh = fopen($path, 'r');
        if (! $fh) {
            return response()->json(['message' => 'Could not read the uploaded file.'], 422);
        }

        $header = fgetcsv($fh);
        if (! $header) {
            fclose($fh);

            return response()->json(['message' => 'The template file is empty.'], 422);
        }

        $header = array_map(fn ($h) => Str::slug(trim((string) $h), '_'), $header);
        $typeIdx = array_search('type', $header, true);
        $labelIdx = array_search('label', $header, true);
        $reqIdx = array_search('required', $header, true);

        if ($labelIdx === false) {
            fclose($fh);

            return response()->json(['message' => 'Template must include a label column. Use type,label,required.'], 422);
        }

        $questions = [];
        while (($row = fgetcsv($fh)) !== false) {
            if (! is_array($row) || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $label = trim((string) ($row[$labelIdx] ?? ''));
            if ($label === '') {
                continue;
            }
            $typeRaw = strtolower(trim((string) ($typeIdx !== false ? ($row[$typeIdx] ?? 'text') : 'text')));
            $type = in_array($typeRaw, ['checkbox', 'yes/no', 'yesno', 'yn', 'boolean'], true) ? 'checkbox' : 'text';
            $reqRaw = $reqIdx !== false ? strtolower(trim((string) ($row[$reqIdx] ?? ''))) : '';
            $required = in_array($reqRaw, ['1', 'true', 'yes', 'y', 'required'], true);

            $questions[] = [
                'id' => 'q_'.Str::lower(Str::random(10)),
                'type' => $type,
                'label' => $label,
                'required' => $required,
            ];
        }
        fclose($fh);

        if ($questions === []) {
            return response()->json(['message' => 'No questions found in the template. Add rows with a label.'], 422);
        }

        return response()->json([
            'message' => 'Template loaded. Click Save form to keep it.',
            'title' => $task->title ? ($task->title.' Checklist') : 'Checklist Form',
            'questions' => $questions,
        ]);
    }

    public function history(int $automateTaskId)
    {
        $form = AutomateTaskChecklistForm::query()
            ->where('automate_task_id', $automateTaskId)
            ->first();

        if (! $form) {
            return response()->json([
                'automate_task_id' => $automateTaskId,
                'form' => null,
                'submissions' => [],
            ]);
        }

        $questions = collect($form->questions ?? [])->keyBy('id');

        $rows = AutomateTaskChecklistSubmission::query()
            ->with('submitter:id,name,email')
            ->where('form_id', $form->id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (AutomateTaskChecklistSubmission $s) use ($questions) {
                $answerRows = [];
                foreach (($s->answers ?? []) as $qid => $val) {
                    $q = $questions->get($qid);
                    $answerRows[] = [
                        'question_id' => $qid,
                        'label' => $q['label'] ?? $qid,
                        'type' => $q['type'] ?? 'text',
                        'value' => $val,
                    ];
                }

                return [
                    'id' => $s->id,
                    'submitted_at' => optional($s->submitted_at)->toDateTimeString(),
                    'submitted_by' => $s->submitter?->name ?? 'Unknown',
                    'answers' => $answerRows,
                ];
            });

        return response()->json([
            'automate_task_id' => $automateTaskId,
            'form' => AutomatedTaskChecklistIds::formPayload($form),
            'submissions' => $rows,
        ]);
    }

    public function showByClId(string $clId)
    {
        $form = AutomatedTaskChecklistIds::findFormByClId($clId);
        if (! $form) {
            return response()->json(['message' => 'Checklist not found.'], 404);
        }

        $task = DB::table('automate_tasks')->where('id', $form->automate_task_id)->first();

        return response()->json([
            'cl_id' => AutomatedTaskChecklistIds::ensureOnForm($form),
            'automate_task_id' => (int) $form->automate_task_id,
            'task_title' => $task->title ?? '',
            'form' => AutomatedTaskChecklistIds::formPayload($form),
        ]);
    }
}
