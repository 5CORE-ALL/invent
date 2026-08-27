<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TemuAdsApiReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Persist Temu listing-create rejects and open a one-time Task Manager task.
 */
class TemuAdCreateRejectService
{
    public const ASSIGNOR = 'mgr-advertisement@5core.com';

    public const ASSIGNEE = 'listing@5core.com';

    public const GROUP = 'temu1';

    public const TITLE_PREFIX = 'Fix Listing.. ';

    public function __construct(
        protected TemuApiService $temuApi,
        protected TaskWhatsAppNotificationService $taskWhatsApp
    ) {}

    /**
     * @return array{rejected: bool, task_id: ?int, title: ?string}
     */
    public function handleFailedCreate(string $goodsId, ?string $sku, string $message, mixed $errorCode = null): array
    {
        $empty = ['rejected' => false, 'task_id' => null, 'title' => null];
        if (! $this->temuApi->isListingCreateReject($message, $errorCode)) {
            return $empty;
        }

        $this->persistReject($goodsId, $message);
        $skuName = trim((string) $sku);
        if ($skuName === '') {
            $skuName = (string) (TemuAdsApiReport::query()->where('goods_id', $goodsId)->value('sku') ?? '');
        }
        if ($skuName === '') {
            $skuName = $goodsId;
        }

        $task = $this->createFixListingTask($skuName, $goodsId, $message);

        return [
            'rejected' => true,
            'task_id' => $task['id'] ?? null,
            'title' => $task['title'] ?? (self::TITLE_PREFIX.$skuName),
        ];
    }

    public function clearReject(string $goodsId): void
    {
        if ($goodsId === '' || ! $this->hasRejectColumns()) {
            return;
        }

        TemuAdsApiReport::query()->where('goods_id', $goodsId)->update([
            'ad_create_reject' => null,
            'ad_create_reject_at' => null,
        ]);
    }

    public function persistReject(string $goodsId, string $message): void
    {
        if ($goodsId === '' || ! $this->hasRejectColumns()) {
            return;
        }

        TemuAdsApiReport::query()->where('goods_id', $goodsId)->update([
            'ad_create_reject' => substr(trim($message) !== '' ? trim($message) : 'Temu rejected this listing for ads.', 0, 500),
            'ad_create_reject_at' => now(),
        ]);
    }

    /**
     * @return array{id:?int,title:string,created:bool}
     */
    public function createFixListingTask(string $sku, string $goodsId, string $message): array
    {
        $title = self::TITLE_PREFIX.trim($sku);
        $marker = 'temu1_ad_reject_'.$goodsId;

        $existing = Task::query()
            ->where('link8', $marker)
            ->whereNotIn('status', ['Done', 'Archived'])
            ->first();
        if ($existing) {
            return [
                'id' => (int) $existing->id,
                'title' => (string) $existing->title,
                'created' => false,
            ];
        }

        try {
            $startDate = now();
            $dueDate = $startDate->copy()->addDays(5);
            $description = 'Temu rejected this listing for ads.'
                .' SKU: '.$sku.'.'
                .' Goods ID: '.$goodsId.'.'
                .' Reason: '.(trim($message) !== '' ? trim($message) : 'listing not eligible.');

            $taskData = [
                'title' => $title,
                'description' => $description,
                'group' => self::GROUP,
                'priority' => 'High',
                'assignor' => self::ASSIGNOR,
                'assign_to' => self::ASSIGNEE,
                'status' => 'Todo',
                'eta_time' => 30,
                'start_date' => $startDate,
                'completion_date' => $dueDate,
                'due_date' => $dueDate,
                'completion_day' => 0,
                'etc_done' => 0,
                'is_missed' => 0,
                'is_missed_track' => 0,
                'workspace' => 0,
                'order' => 0,
                'task_id' => '',
                'link1' => url('/temu/ads'),
                'link2' => '',
                'link3' => '',
                'link4' => '',
                'link5' => '',
                'link6' => '',
                'link7' => '',
                'link8' => $marker,
                'link9' => '',
                'is_data_from' => 0,
                'is_automate_task' => 0,
                'task_type' => 'manual',
                'rework_reason' => '',
                'delete_rating' => 0,
                'delete_feedback' => '',
                'split_tasks' => 0,
            ];
            if (Schema::hasColumn('tasks', 'is_corrective_action')) {
                $taskData['is_corrective_action'] = 0;
            }

            $task = Task::create($taskData);

            try {
                $this->taskWhatsApp->notifyNewTaskAssigned($task);
            } catch (\Throwable $e) {
                Log::warning('Temu ad reject task WhatsApp failed', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'id' => (int) $task->id,
                'title' => $title,
                'created' => true,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to create Temu Fix Listing task', [
                'goods_id' => $goodsId,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return [
                'id' => null,
                'title' => $title,
                'created' => false,
            ];
        }
    }

    private function hasRejectColumns(): bool
    {
        return Schema::hasTable('temu_ads_api_reports')
            && Schema::hasColumn('temu_ads_api_reports', 'ad_create_reject');
    }
}
