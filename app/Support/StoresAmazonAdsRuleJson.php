<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One table for every Amazon ads rule, same shape as ebay_sbid_rules:
 * unique key + JSON rule, written with updateOrInsert.
 */
trait StoresAmazonAdsRuleJson
{
    private static function readStoredRule(string $key, ?string $legacyTable = null): ?array
    {
        self::ensureRulesTable();
        $decoded = self::decodeStoredRule(
            DB::table('amazon_ads_rules')->where('key', $key)->value('rule')
        );
        if (is_array($decoded) && $decoded !== []) {
            return $decoded;
        }
        if ($legacyTable === null || ! Schema::hasTable($legacyTable)) {
            return null;
        }
        $legacy = self::decodeStoredRule(DB::table($legacyTable)->orderBy('id')->value('rule'));
        if (! is_array($legacy) || $legacy === []) {
            return null;
        }
        self::storeRuleJson($key, $legacy);

        return $legacy;
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
    private static function storeRuleJson(string $key, array $rule): void
    {
        self::ensureRulesTable();
        $exists = DB::table('amazon_ads_rules')->where('key', $key)->exists();
        $payload = [
            'rule' => json_encode($rule),
            'updated_at' => now(),
        ];
        if (! $exists) {
            $payload['created_at'] = now();
            $payload['key'] = $key;
            DB::table('amazon_ads_rules')->insert($payload);

            return;
        }
        DB::table('amazon_ads_rules')->where('key', $key)->update($payload);
    }

    private static function ensureRulesTable(): void
    {
        if (Schema::hasTable('amazon_ads_rules')) {
            return;
        }
        try {
            Schema::create('amazon_ads_rules', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->json('rule');
                $table->timestamps();
            });
        } catch (\Throwable $e) {
            if (! Schema::hasTable('amazon_ads_rules')) {
                throw $e;
            }
        }
    }
}
