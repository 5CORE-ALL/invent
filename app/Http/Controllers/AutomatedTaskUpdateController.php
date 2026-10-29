<?php

namespace App\Http\Controllers;

use App\Models\AutomateTaskUpdateForm;
use App\Models\AutomateTaskUpdateSubmission;
use App\Support\AutomatedTaskAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Update" structure + "Update Reports" for automated task templates.
 * Works like the Checklist (CL / CL-R) feature but has its own tables.
 */
class AutomatedTaskUpdateController extends Controller
{
    public static function canManage($user): bool
    {
        return AutomatedTaskChecklistController::canManageChecklist($user);
    }

    private function formPayload(AutomateTaskUpdateForm $form): array
    {
        return [
            'id' => $form->id,
            'title' => $form->title,
            'questions' => is_array($form->questions) ? $form->questions : [],
        ];
    }

    /**
     * @return object|JsonResponse
     */
    private function visibleTemplate(int $automateTaskId)
    {
        $task = DB::table('automate_tasks')->where('id', $automateTaskId)->first();
        if (! $task) {
            return response()->json(['message' => 'Automated task not found.'], 404);
        }

        $user = Auth::user();
        if (! $user || ! AutomatedTaskAccess::canView($user, $task)) {
            return response()->json(['message' => 'You are not allowed to view this automated task.'], 403);
        }

        return $task;
    }

    public function show(int $automateTaskId)
    {
        $task = DB::table('automate_tasks')->where('id', $automateTaskId)->first();
        if (! $task) {
            return response()->json(['message' => 'Automated task not found.'], 404);
        }

        $form = AutomateTaskUpdateForm::query()->where('automate_task_id', $automateTaskId)->first();

        $sopLink = trim((string) ($task->link3 ?? ''));
        if ($sopLink !== '' && ! preg_match('#^https?://#i', $sopLink)) {
            $sopLink = '';
        }

        return response()->json([
            'automate_task_id' => $automateTaskId,
            'task_title' => $task->title ?? '',
            'sop_link' => $sopLink !== '' ? $sopLink : null,
            'can_manage' => self::canManage(Auth::user()),
            'form' => $form ? $this->formPayload($form) : null,
            'submission_count' => $form
                ? AutomateTaskUpdateSubmission::query()->where('form_id', $form->id)->count()
                : 0,
        ]);
    }

    public function save(Request $request, int $automateTaskId)
    {
        $user = Auth::user();
        if (! self::canManage($user)) {
            return response()->json(['message' => 'Only Senior and Director roles can create or edit update forms.'], 403);
        }

        $task = $this->visibleTemplate($automateTaskId);
        if ($task instanceof JsonResponse) {
            return $task;
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

        $title = $validated['title'] ?? ($task->title ? ($task->title.' Update') : 'Update Form');

        $form = AutomateTaskUpdateForm::query()->firstOrNew(['automate_task_id' => $automateTaskId]);
        if (! $form->exists) {
            $form->created_by = $user->id;
        }
        $form->title = $title;
        $form->questions = $questions;
        $form->updated_by = $user->id;
        $form->save();

        return response()->json([
            'message' => 'Update form saved.',
            'form' => $this->formPayload($form->fresh() ?: $form),
        ]);
    }

    public function submit(Request $request, int $automateTaskId)
    {
        $form = AutomateTaskUpdateForm::query()->where('automate_task_id', $automateTaskId)->first();
        if (! $form) {
            return response()->json(['message' => 'No update form is attached to this task.'], 422);
        }

        $questions = $form->questions ?? [];
        if (! is_array($questions) || count($questions) === 0) {
            return response()->json(['message' => 'Update form has no questions.'], 422);
        }

        $validated = $request->validate(['answers' => 'required|array']);
        $answersIn = $validated['answers'];
        $normalized = [];

        foreach ($questions as $q) {
            $qid = (string) ($q['id'] ?? '');
            if ($qid === '') {
                continue;
            }
            $label = (string) ($q['label'] ?? $qid);
            $raw = $answersIn[$qid] ?? null;

            if (($q['type'] ?? 'text') === 'checkbox') {
                $isYes = null;
                $actions = [];
                $corrective = '';

                if (is_array($raw)) {
                    if (array_key_exists('answer', $raw)) {
                        $parsed = filter_var($raw['answer'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        $isYes = $parsed === null ? (bool) $raw['answer'] : $parsed;
                    }
                    foreach ((array) ($raw['actions'] ?? []) as $act) {
                        $act = trim((string) $act);
                        if ($act !== '') {
                            $actions[] = $act;
                        }
                    }
                    $corrective = trim((string) ($raw['corrective_action'] ?? ''));
                } elseif ($raw === 'yes' || $raw === 'no') {
                    $isYes = $raw === 'yes';
                } elseif ($raw !== null) {
                    $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    $isYes = $parsed === null ? (bool) $raw : $parsed;
                }

                if ($isYes === null) {
                    return response()->json(['message' => 'Select Yes or No for: '.$label], 422);
                }
                if (! $isYes && count($actions) === 0 && $corrective === '') {
                    return response()->json([
                        'message' => 'For "'.$label.'", enter Action or Corrective action (minimum one).',
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
                if (! empty($q['required']) && $val === '') {
                    return response()->json(['message' => 'Please complete required field: '.$label], 422);
                }
                $normalized[$qid] = $val;
            }
        }

        $submission = AutomateTaskUpdateSubmission::create([
            'form_id' => $form->id,
            'automate_task_id' => $automateTaskId,
            'submitted_by' => Auth::id(),
            'answers' => $normalized,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Update submitted.',
            'submission_id' => $submission->id,
            'submission_count' => AutomateTaskUpdateSubmission::query()->where('form_id', $form->id)->count(),
        ]);
    }

    public function downloadTemplate(int $automateTaskId)
    {
        $task = $this->visibleTemplate($automateTaskId);
        if ($task instanceof JsonResponse) {
            return $task;
        }

        $form = AutomateTaskUpdateForm::query()->where('automate_task_id', $automateTaskId)->first();
        $questions = is_array($form?->questions) ? $form->questions : [];
        if ($questions === []) {
            $questions = [
                ['type' => 'checkbox', 'label' => 'Example yes/no question', 'required' => false],
                ['type' => 'text', 'label' => 'Example text question', 'required' => false],
            ];
        }

        $safeTitle = Str::slug((string) ($form?->title ?? $task->title ?? 'update'), '-');
        $filename = 'update-template-'.($safeTitle !== '' ? $safeTitle : $automateTaskId).'.csv';

        return response()->streamDownload(function () use ($questions) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['type', 'label', 'required']);
            foreach ($questions as $q) {
                $type = strtolower((string) ($q['type'] ?? 'text'));
                fputcsv($out, [
                    $type === 'checkbox' ? 'checkbox' : 'text',
                    (string) ($q['label'] ?? ''),
                    ! empty($q['required']) ? '1' : '0',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function uploadTemplate(Request $request, int $automateTaskId)
    {
        if (! self::canManage(Auth::user())) {
            return response()->json(['message' => 'Only Senior and Director roles can upload update templates.'], 403);
        }

        $task = $this->visibleTemplate($automateTaskId);
        if ($task instanceof JsonResponse) {
            return $task;
        }

        $request->validate(['file' => 'required|file|max:2048']);

        $ext = strtolower((string) $request->file('file')->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt'], true)) {
            return response()->json(['message' => 'Upload a CSV template (type,label,required).'], 422);
        }

        $fh = fopen($request->file('file')->getRealPath(), 'r');
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
            $reqRaw = $reqIdx !== false ? strtolower(trim((string) ($row[$reqIdx] ?? ''))) : '';

            $questions[] = [
                'id' => 'q_'.Str::lower(Str::random(10)),
                'type' => in_array($typeRaw, ['checkbox', 'yes/no', 'yesno', 'yn', 'boolean'], true) ? 'checkbox' : 'text',
                'label' => $label,
                'required' => in_array($reqRaw, ['1', 'true', 'yes', 'y', 'required'], true),
            ];
        }
        fclose($fh);

        if ($questions === []) {
            return response()->json(['message' => 'No questions found in the template. Add rows with a label.'], 422);
        }

        return response()->json([
            'message' => 'Template loaded. Click Save form to keep it.',
            'title' => $task->title ? ($task->title.' Update') : 'Update Form',
            'questions' => $questions,
        ]);
    }

    public function history(int $automateTaskId)
    {
        $task = $this->visibleTemplate($automateTaskId);
        if ($task instanceof JsonResponse) {
            return $task;
        }

        $form = AutomateTaskUpdateForm::query()->where('automate_task_id', $automateTaskId)->first();
        if (! $form) {
            return response()->json(['automate_task_id' => $automateTaskId, 'form' => null, 'submissions' => []]);
        }

        $questions = collect($form->questions ?? [])->keyBy('id');

        $rows = AutomateTaskUpdateSubmission::query()
            ->with('submitter:id,name,email')
            ->where('form_id', $form->id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (AutomateTaskUpdateSubmission $s) use ($questions) {
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
            'form' => $this->formPayload($form),
            'submissions' => $rows,
        ]);
    }
}
