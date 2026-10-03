<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ImageMasterPushHistory
{
    private const TABLE = 'image_master_push_histories';

    private static ?bool $tableExists = null;

    /**
     * @param  array{id?: int|null, name?: string|null, email?: string|null}|null  $user
     */
    public static function record(
        string $sku,
        ?string $jobId,
        ?array $user,
        string $marketplace,
        bool $success,
        string $mode,
        int $imageCount,
        string $message
    ): void {
        if (! self::tableExists() || trim($sku) === '') {
            return;
        }

        try {
            DB::table(self::TABLE)->insert([
                'sku' => trim($sku),
                'job_id' => $jobId,
                'user_id' => isset($user['id']) ? (int) $user['id'] : null,
                'user_name' => $user['name'] ?? null,
                'user_email' => $user['email'] ?? null,
                'marketplace' => $marketplace,
                'success' => $success,
                'mode' => $mode,
                'image_count' => max(0, min(65535, $imageCount)),
                'message' => mb_substr($message, 0, 1000),
                'pushed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ImageMasterPushHistory record failed', ['sku' => $sku, 'marketplace' => $marketplace, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Latest push per SKU (all marketplaces of that push) plus the last successful push per marketplace.
     *
     * @param  list<string>  $skus
     * @return array<string, array{last_at: string, user_id: int|null, user_name: string, user_email: string, mode: string, pushed: list<array{marketplace: string, success: bool, message: string}>, last_success: array<string, string>}>
     */
    public static function summaryForSkus(array $skus): array
    {
        if (! self::tableExists()) {
            return [];
        }
        $skus = array_values(array_unique(array_filter(array_map(static fn ($s) => trim((string) $s), $skus))));
        if ($skus === []) {
            return [];
        }

        $out = [];
        try {
            foreach (array_chunk($skus, 1000) as $chunk) {
                $latestIds = DB::table(self::TABLE)
                    ->whereIn('sku', $chunk)
                    ->groupBy('sku')
                    ->selectRaw('MAX(id) as id')
                    ->pluck('id')
                    ->all();
                if ($latestIds === []) {
                    continue;
                }
                $latestRows = DB::table(self::TABLE)->whereIn('id', $latestIds)->get();
                $jobIds = $latestRows->pluck('job_id')->filter()->unique()->values()->all();
                $batchRows = $jobIds === []
                    ? collect()
                    : DB::table(self::TABLE)->whereIn('job_id', $jobIds)->orderBy('id')
                        ->get(['sku', 'job_id', 'marketplace', 'success', 'message'])
                        ->groupBy(fn ($r) => $r->job_id.'|'.strtoupper(trim((string) $r->sku)));
                $successRows = DB::table(self::TABLE)
                    ->whereIn('sku', $chunk)
                    ->where('success', true)
                    ->groupBy('sku', 'marketplace')
                    ->selectRaw('sku, marketplace, MAX(pushed_at) as last_at')
                    ->get()
                    ->groupBy(fn ($r) => strtoupper(trim((string) $r->sku)));

                foreach ($latestRows as $latest) {
                    $skuKey = strtoupper(trim((string) $latest->sku));
                    $batch = $latest->job_id
                        ? ($batchRows->get($latest->job_id.'|'.$skuKey) ?? collect([$latest]))
                        : collect([$latest]);

                    $pushed = [];
                    foreach ($batch->keyBy('marketplace') as $r) {
                        $pushed[] = [
                            'marketplace' => (string) $r->marketplace,
                            'success' => (bool) $r->success,
                            'message' => mb_substr((string) ($r->message ?? ''), 0, 200),
                        ];
                    }

                    $lastSuccess = [];
                    foreach ($successRows->get($skuKey, collect()) as $r) {
                        $lastSuccess[(string) $r->marketplace] = (string) $r->last_at;
                    }

                    $out[(string) $latest->sku] = [
                        'last_at' => (string) $latest->pushed_at,
                        'user_id' => $latest->user_id !== null ? (int) $latest->user_id : null,
                        'user_name' => (string) ($latest->user_name ?? ''),
                        'user_email' => (string) ($latest->user_email ?? ''),
                        'mode' => (string) ($latest->mode ?? ''),
                        'pushed' => $pushed,
                        'last_success' => $lastSuccess,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ImageMasterPushHistory summary failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    private static function tableExists(): bool
    {
        if (self::$tableExists === null) {
            try {
                self::$tableExists = Schema::hasTable(self::TABLE);
            } catch (\Throwable) {
                self::$tableExists = false;
            }
        }

        return self::$tableExists;
    }
}
