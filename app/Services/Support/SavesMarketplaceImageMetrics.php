<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

trait SavesMarketplaceImageMetrics
{
    /**
     * @param  list<string>  $images
     */
    protected function saveImageUrlsToMetricsRow(string $table, string $sku, array $images): bool
    {
        try {
            if ($sku === '' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
                return false;
            }

            $payload = $images === []
                ? null
                : json_encode(array_values($images), JSON_UNESCAPED_SLASHES);
            if ($images !== [] && $payload === false) {
                return false;
            }

            $update = [];
            if (Schema::hasColumn($table, 'image_master_json')) {
                $update['image_master_json'] = $payload;
            }
            if (Schema::hasColumn($table, 'image_urls')) {
                $update['image_urls'] = $payload;
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
            Log::warning('Marketplace image metrics save failed', [
                'table' => $table,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
