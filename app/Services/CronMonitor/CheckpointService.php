<?php

namespace App\Services\CronMonitor;

use App\Models\CronExecutionCheckpoint;
use App\Models\CronExecutionLog;

class CheckpointService
{
    public function save(
        string $jobName,
        mixed $cursor,
        int $processedOffset = 0,
        ?string $command = null,
        ?CronExecutionLog $log = null,
        array $meta = []
    ): CronExecutionCheckpoint {
        $encoded = is_string($cursor) ? $cursor : json_encode($cursor);

        $checkpoint = CronExecutionCheckpoint::query()->updateOrCreate(
            ['job_name' => $jobName],
            [
                'execution_log_id' => $log?->id,
                'command' => $command ?? $log?->command,
                'cursor' => $encoded,
                'processed_offset' => $processedOffset,
                'meta' => $meta,
            ]
        );

        if ($log) {
            $log->update([
                'checkpoint' => [
                    'cursor' => $cursor,
                    'processed_offset' => $processedOffset,
                    'saved_at' => now()->toDateTimeString(),
                ],
                'resume_from' => $processedOffset,
            ]);
        }

        return $checkpoint;
    }

    public function load(string $jobName): ?CronExecutionCheckpoint
    {
        return CronExecutionCheckpoint::query()
            ->where('job_name', $jobName)
            ->orderByDesc('updated_at')
            ->first();
    }

    public function clear(string $jobName): void
    {
        CronExecutionCheckpoint::query()->where('job_name', $jobName)->delete();
    }

    public function resumeOffset(string $jobName): int
    {
        return (int) ($this->load($jobName)?->processed_offset ?? 0);
    }

    public function resumeCursor(string $jobName): mixed
    {
        return $this->load($jobName)?->decodedCursor();
    }
}
