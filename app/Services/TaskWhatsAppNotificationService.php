<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Task WhatsApp notifications. Uses templates (wa/api/v1/template/msg) when
 * WHATSAPP_USE_TEMPLATE_FOR_TASKS=true so messages deliver outside 24h.
 *
 * Template param order (must match your Gupshup templates):
 * - task_assigned: [assigneeName, assignorName, taskTitle, startDate, dueDate, eta]
 * - task_done: [assignorName, assigneeName, taskTitle, etc, atc]
 * - task_rework: [assigneeName, assignorName, taskTitle, etc, atc, reworkReason]
 * - task_updated: [assigneeName, taskTitle, assignorName, startDate, dueDate, eta]
 */
class TaskWhatsAppNotificationService
{
    public function __construct(
        protected WhatsAppService $whatsApp
    ) {}

    /**
     * Message 1 – New task assigned (to assignee).
     * Send when a new task is created and assigned.
     * @return 'sent'|'skipped_no_assignee'|'skipped_no_user'|'skipped_no_phone'
     */
    public function notifyNewTaskAssigned(Task $task): string
    {
        if (!$task->assign_to) {
            return 'skipped_no_assignee';
        }

        $assignee = User::where('email', $task->assign_to)->first();
        if (!$assignee) {
            Log::warning('TaskWhatsApp: assignee user not found (email)', ['task_id' => $task->id, 'assign_to' => $task->assign_to]);
            return 'skipped_no_user';
        }

        $phone = WhatsAppService::cleanPhone($assignee->phone ?? null);
        if (!$phone) {
            Log::warning('TaskWhatsApp: assignee has no phone — add users.phone for delivery', ['task_id' => $task->id, 'assignee_email' => $task->assign_to]);
            return 'skipped_no_phone';
        }

        $assigneeName = $this->fullName($assignee->name, $task->assign_to);
        $assignorName = $this->assignorName($task);
        $startDate = $task->start_date ? Carbon::parse($task->start_date)->format('d M Y') : '-';
        $dueDate = $task->due_date ? Carbon::parse($task->due_date)->format('d M Y') : '-';
        $eta = (int) ($task->eta_time ?? 0);

        $text = "Hello {$assigneeName},\n\n"
            . "A new task has been assigned by {$assignorName}.\n\n"
            . "Task Name: {$task->title}\n"
            . "Start Date: {$startDate}\n"
            . "Due Date: {$dueDate}\n"
            . "ETA: {$eta} minutes\n\n"
            . "Please complete it before the deadline.\n\n"
            . "Thank you, Team";

        $templateParams = [$assigneeName, $assignorName, $task->title, $startDate, $dueDate, (string) $eta];
        Log::info('TaskWhatsApp: sending new-task-assigned', ['task_id' => $task->id, 'to' => $phone, 'assignee' => $assigneeName]);
        $this->sendToRecipient($phone, $text, 'task_assigned', $templateParams);
        return 'sent';
    }

    /**
     * Message 2 – Task done (to assignor).
     * Send when assignee marks the task as done.
     */
    public function notifyTaskDone(Task $task): void
    {
        if (!$task->assignor) {
            return;
        }

        $assignor = User::where('email', $task->assignor)->first();
        if (!$assignor) {
            Log::warning('TaskWhatsApp: assignor user not found (email)', ['task_id' => $task->id, 'assignor' => $task->assignor]);
            return;
        }

        $phone = WhatsAppService::cleanPhone($assignor->phone ?? null);
        if (!$phone) {
            Log::warning('TaskWhatsApp: assignor has no phone — add users.phone for delivery', ['task_id' => $task->id, 'assignor_email' => $task->assignor]);
            return;
        }

        $assignorName = $this->fullName($assignor->name, $task->assignor);
        $assigneeName = $this->assigneeName($task);
        $etc = (int) ($task->eta_time ?? 0);
        $atc = (int) ($task->etc_done ?? 0);

        $text = "Hello {$assignorName},\n\n"
            . "Your assigned task is done by {$assigneeName}.\n\n"
            . "Task Name: {$task->title}\n"
            . "ETC: {$etc} Min\n"
            . "ATC: {$atc} Min\n\n"
            . "Please review the task and delete/close it.\n\n"
            . "Thank you, Team";

        $templateParams = [$assignorName, $assigneeName, $task->title, (string) $etc, (string) $atc];
        $this->sendToRecipient($phone, $text, 'task_done', $templateParams);
    }

    /**
     * Message 3 – Rework (to assignee).
     * Send when assignor marks the task as rework.
     */
    public function notifyRework(Task $task): void
    {
        if (!$task->assign_to) {
            return;
        }

        $assignee = User::where('email', $task->assign_to)->first();
        if (!$assignee) {
            Log::warning('TaskWhatsApp: assignee user not found (email)', ['task_id' => $task->id, 'assign_to' => $task->assign_to]);
            return;
        }

        $phone = WhatsAppService::cleanPhone($assignee->phone ?? null);
        if (!$phone) {
            Log::warning('TaskWhatsApp: assignee has no phone — add users.phone for delivery', ['task_id' => $task->id, 'assignee_email' => $task->assign_to]);
            return;
        }

        $assigneeName = $this->fullName($assignee->name, $task->assign_to);
        $assignorName = $this->assignorName($task);
        $etc = (int) ($task->eta_time ?? 0);
        $atc = (int) ($task->etc_done ?? 0);
        $reason = $task->rework_reason ? trim($task->rework_reason) : 'No feedback provided.';

        $text = "Hello {$assigneeName},\n\n"
            . "Your assigned task has been marked as rework by {$assignorName}.\n\n"
            . "Task Name: {$task->title}\n"
            . "ETC: {$etc} Min, ATC: {$atc} Min\n"
            . "Kindly review and take necessary action. Feedback: {$reason}\n\n"
            . "Thank you, Team";

        $templateParams = [$assigneeName, $assignorName, $task->title, (string) $etc, (string) $atc, $reason];
        $this->sendToRecipient($phone, $text, 'task_rework', $templateParams);
    }

    /**
     * Message 4 – Task updated (to assignee).
     * Send when task details are updated (e.g. dates, ETA).
     */
    public function notifyTaskUpdated(Task $task): void
    {
        if (!$task->assign_to) {
            return;
        }

        $assignee = User::where('email', $task->assign_to)->first();
        if (!$assignee) {
            Log::warning('TaskWhatsApp: assignee user not found (email)', ['task_id' => $task->id, 'assign_to' => $task->assign_to]);
            return;
        }

        $phone = WhatsAppService::cleanPhone($assignee->phone ?? null);
        if (!$phone) {
            Log::warning('TaskWhatsApp: assignee has no phone — add users.phone for delivery', ['task_id' => $task->id, 'assignee_email' => $task->assign_to]);
            return;
        }

        $assigneeName = $this->fullName($assignee->name, $task->assign_to);
        $assignorName = $this->assignorName($task);
        $startDate = $task->start_date ? Carbon::parse($task->start_date)->format('d M Y') : '-';
        $dueDate = $task->due_date ? Carbon::parse($task->due_date)->format('d M Y') : '-';
        $eta = (int) ($task->eta_time ?? 0);
        $title = $task->title;

        $text = "Hello {$assigneeName},\n\n"
            . "Your assigned task \"{$title}\" has been updated by {$assignorName}.\n\n"
            . "Task Name, Start Date, Due Date, ETA: {$title}, {$startDate}, {$dueDate}, {$eta} min\n\n"
            . "Please review the changes. If you have concerns, contact your assignor.\n\n"
            . "Thank you, Team";

        $templateParams = [$assigneeName, $title, $assignorName, $startDate, $dueDate, (string) $eta];
        $this->sendToRecipient($phone, $text, 'task_updated', $templateParams);
    }

    protected function assignorName(Task $task): string
    {
        if (!$task->assignor) {
            return 'Unknown';
        }
        $u = User::where('email', $task->assignor)->first();
        return $this->fullName($u?->name, $task->assignor);
    }

    protected function assigneeName(Task $task): string
    {
        if (!$task->assign_to) {
            return 'Unknown';
        }
        $u = User::where('email', $task->assign_to)->first();
        return $this->fullName($u?->name, $task->assign_to);
    }

    /** Trimmed display name; fallback to email if empty. */
    protected function fullName(?string $name, string $fallback): string
    {
        $n = trim((string) ($name ?? ''));
        return $n !== '' ? $n : $fallback;
    }

    /**
     * Send to one recipient. Use template if configured (delivers outside 24h); else session text.
     */
    protected function sendToRecipient(string $phone, string $text, string $templateKey, array $templateParams = []): void
    {
        if (!$this->whatsApp->isEnabled()) {
            Log::warning('TaskWhatsApp: WhatsApp disabled or not configured — check WHATSAPP_ENABLED and Gupshup keys.');
            return;
        }

        $useTemplate = (bool) config('services.whatsapp.use_template_for_tasks', true);
        $templateIdKey = 'template_id_' . $templateKey;
        $templateId = config("services.whatsapp.gupshup.{$templateIdKey}");

        if ($useTemplate && $templateId && $templateParams !== []) {
            Log::info('TaskWhatsApp: sending via template', ['template' => $templateKey, 'to' => $phone]);
            $this->whatsApp->sendTemplate($phone, $templateId, $templateParams);
            return;
        }

        $this->whatsApp->sendText($phone, $text);
    }
}
