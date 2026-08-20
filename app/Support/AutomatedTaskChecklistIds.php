<?php

namespace App\Support;

use App\Models\AutomateTaskChecklistForm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stable public checklist id (CL-{form id}) assigned when a form is created.
 * Copied onto fired task instances so other screens can look it up.
 */
class AutomatedTaskChecklistIds
{
    public static function format(int $formId): string
    {
        return 'CL-'.$formId;
    }

    public static function ensureOnForm(AutomateTaskChecklistForm $form): string
    {
        if (! $form->id) {
            $form->save();
        }

        $clId = self::format((int) $form->id);
        if (! Schema::hasColumn('automate_task_checklist_forms', 'cl_id')) {
            return $clId;
        }

        $existing = trim((string) ($form->cl_id ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        $form->cl_id = $clId;
        $form->save();

        return $clId;
    }

    /**
     * @return array{form_id:int,cl_id:string,automate_task_id:int,title:?string}|null
     */
    public static function metaForAutomateTask(?int $automateTaskId): ?array
    {
        if (! $automateTaskId || ! Schema::hasTable('automate_task_checklist_forms')) {
            return null;
        }

        $form = AutomateTaskChecklistForm::query()
            ->where('automate_task_id', $automateTaskId)
            ->first();

        if (! $form) {
            return null;
        }

        return [
            'form_id' => (int) $form->id,
            'cl_id' => self::ensureOnForm($form),
            'automate_task_id' => (int) $form->automate_task_id,
            'title' => $form->title,
        ];
    }

    public static function findFormByClId(string $clId): ?AutomateTaskChecklistForm
    {
        $clId = strtoupper(trim($clId));
        if ($clId === '') {
            return null;
        }

        if (Schema::hasColumn('automate_task_checklist_forms', 'cl_id')) {
            $form = AutomateTaskChecklistForm::query()->where('cl_id', $clId)->first();
            if ($form) {
                return $form;
            }
        }

        if (preg_match('/^CL-(\d+)$/', $clId, $m)) {
            return AutomateTaskChecklistForm::query()->find((int) $m[1]);
        }

        return null;
    }

    /**
     * @return array{id:int,cl_id:string,automate_task_id:int,title:?string,questions:array,updated_at:?string}
     */
    public static function formPayload(AutomateTaskChecklistForm $form): array
    {
        return [
            'id' => (int) $form->id,
            'cl_id' => self::ensureOnForm($form),
            'automate_task_id' => (int) $form->automate_task_id,
            'title' => $form->title,
            'questions' => $form->questions ?? [],
            'updated_at' => optional($form->updated_at)->toDateTimeString(),
        ];
    }

    /**
     * Stamp CL id onto the template and any already-fired instances
     * (empty link7 / cl_id only — do not overwrite a real URL).
     */
    public static function applyToTemplateAndFiredTasks(int $automateTaskId, string $clId): void
    {
        $clId = trim($clId);
        if ($clId === '' || $automateTaskId < 1) {
            return;
        }

        if (Schema::hasTable('automate_tasks')) {
            $template = DB::table('automate_tasks')->where('id', $automateTaskId)->first();
            if ($template && trim((string) ($template->link7 ?? '')) === '') {
                DB::table('automate_tasks')->where('id', $automateTaskId)->update([
                    'link7' => $clId,
                    'updated_at' => now(),
                ]);
            }
        }

        if (! Schema::hasTable('tasks')) {
            return;
        }

        $now = now();
        $base = DB::table('tasks')
            ->where('automate_task_id', $automateTaskId)
            ->whereNull('deleted_at');

        if (Schema::hasColumn('tasks', 'cl_id')) {
            (clone $base)
                ->where(function ($q) {
                    $q->whereNull('cl_id')->orWhere('cl_id', '');
                })
                ->update(['cl_id' => $clId, 'updated_at' => $now]);
        }

        (clone $base)
            ->where(function ($q) {
                $q->whereNull('link7')->orWhere('link7', '');
            })
            ->update(['link7' => $clId, 'updated_at' => $now]);
    }

    /**
     * @param  array<string, mixed>  $taskData
     * @return array<string, mixed>
     */
    public static function mergeIntoTaskInsert(array $taskData, int $automateTaskId): array
    {
        $meta = self::metaForAutomateTask($automateTaskId);
        if (! $meta) {
            return $taskData;
        }

        if (Schema::hasColumn('tasks', 'cl_id')) {
            $taskData['cl_id'] = $meta['cl_id'];
        }

        if (trim((string) ($taskData['link7'] ?? '')) === '') {
            $taskData['link7'] = $meta['cl_id'];
        }

        return $taskData;
    }
}
