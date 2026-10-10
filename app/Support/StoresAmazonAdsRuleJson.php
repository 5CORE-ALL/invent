<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same save path as eBay campaign ads: one JSON column, written with the query
 * builder and read back with json_decode. No model cast and no remembered copy.
 */
trait StoresAmazonAdsRuleJson
{
    private static function readStoredRule(string $table): ?array
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        return self::decodeStoredRule(DB::table($table)->orderBy('id')->value('rule'));
    }

    private static function decodeStoredRule(mixed $raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private static function storeRuleJson(string $table, array $rule): void
    {
        if (! Schema::hasTable($table)) {
            throw new \RuntimeException('Table '.$table.' does not exist. Run migrations.');
        }
        $rowId = DB::table($table)->orderBy('id')->value('id');
        $payload = [
            'rule' => json_encode($rule),
            'updated_at' => now(),
        ];
        if ($rowId === null) {
            $payload['created_at'] = now();
            DB::table($table)->insert($payload);

            return;
        }
        DB::table($table)->where('id', $rowId)->update($payload);
    }
}
