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

    /**
     * Copy the checklist questionnaire from one automated-task template to another.
     * The clone gets its own form row and CL id; submissions are not copied.
     *
     * @return string|null New CL id, or null if the source has no form
     */
    public static function cloneFormToAutomateTask(int $sourceAutomateTaskId, int $targetAutomateTaskId, ?int $actorUserId = null): ?string
    {
        if ($sourceAutomateTaskId < 1 || $targetAutomateTaskId < 1 || $sourceAutomateTaskId === $targetAutomateTaskId) {
            return null;
        }

        if (! Schema::hasTable('automate_task_checklist_forms')) {
            return null;
        }

        $source = AutomateTaskChecklistForm::query()
            ->where('automate_task_id', $sourceAutomateTaskId)
            ->first();

        if (! $source) {
            return null;
        }

        $existing = AutomateTaskChecklistForm::query()
            ->where('automate_task_id', $targetAutomateTaskId)
            ->first();

        $sourceClId = self::ensureOnForm($source);

        if ($existing) {
            $clId = self::ensureOnForm($existing);
            self::restampTemplateLink7($targetAutomateTaskId, $sourceClId, $clId);

            return $clId;
        }

        $except = ['id', 'automate_task_id', 'created_at', 'updated_at'];
        if (Schema::hasColumn('automate_task_checklist_forms', 'cl_id')) {
            $except[] = 'cl_id';
        }

        $clone = $source->replicate($except);
        $clone->automate_task_id = $targetAutomateTaskId;
        $clone->created_by = $actorUserId ?: $source->created_by;
        $clone->updated_by = $actorUserId ?: $source->updated_by;
        if (Schema::hasColumn('automate_task_checklist_forms', 'cl_id')) {
            $clone->cl_id = null;
        }
        $clone->save();

        $clId = self::ensureOnForm($clone);
        self::restampTemplateLink7($targetAutomateTaskId, $sourceClId, $clId);
        self::applyToTemplateAndFiredTasks($targetAutomateTaskId, $clId);

        return $clId;
    }

    /**
     * After a template is duplicated, replace a copied source CL id on link7
     * with the new form's CL id. Leave real URLs untouched.
     */
    protected static function restampTemplateLink7(int $automateTaskId, string $sourceClId, string $newClId): void
    {
        if (! Schema::hasTable('automate_tasks') || $newClId === '') {
            return;
        }

        $template = DB::table('automate_tasks')->where('id', $automateTaskId)->first();
        if (! $template) {
            return;
        }

        $link7 = trim((string) ($template->link7 ?? ''));
        $sourceClId = trim($sourceClId);
        $shouldReplace = $link7 === ''
            || ($sourceClId !== '' && strcasecmp($link7, $sourceClId) === 0);

        if (! $shouldReplace) {
            return;
        }

        DB::table('automate_tasks')->where('id', $automateTaskId)->update([
            'link7' => $newClId,
            'updated_at' => now(),
        ]);
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
