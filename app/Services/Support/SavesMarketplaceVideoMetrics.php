<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

trait SavesMarketplaceVideoMetrics
{
    /**
     * @param  list<string>  $videos
     */
    protected function saveVideoUrlsToMetricsRow(string $table, string $sku, array $videos): bool
    {
        try {
            if ($sku === '' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
                return false;
            }

            $payload = $videos === []
                ? null
                : json_encode(array_values($videos), JSON_UNESCAPED_SLASHES);
            if ($videos !== [] && $payload === false) {
                return false;
            }

            $update = [];
            if (Schema::hasColumn($table, 'video_master_json')) {
                $update['video_master_json'] = $payload;
            }
            if (Schema::hasColumn($table, 'video_urls')) {
                $update['video_urls'] = $payload;
            }
            if ($update === []) {
                return false;
            }
            if (Schema::hasColumn($table, 'updated_at')) {
                $update['updated_at'] = now();
            }

            DB::table($table)->updateOrInsert(['sku' => $sku], $update);
            if (Schema::hasColumn($table, 'created_at')) {
                DB::table($table)->where('sku', $sku)->whereNull('created_at')->update(['created_at' => now()]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Marketplace video metrics save failed', [
                'table' => $table,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
