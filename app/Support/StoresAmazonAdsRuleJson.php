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
    /**
     * Rules are read once per grid row (views, cvr, acos, prc, reviews, dil, inv). Without a memo that is
     * hundreds of identical SELECTs plus an information_schema lookup each time, which dominated the load.
     * A few seconds is long enough to cover one request and short enough that other processes' saves show up.
     */
    private static int $ruleMemoSeconds = 5;

    private static function ruleMemoOn(): bool
    {
        // Unit tests reset tables and rows between cases, so they always read through.
        return ! app()->runningUnitTests();
    }

    /** @var array<string, array{0: float, 1: ?array}> */
    private static array $ruleMemo = [];

    private static bool $rulesTableKnown = false;

    private static function readStoredRule(string $key): ?array
    {
        $memo = self::ruleMemoOn();
        $now = microtime(true);
        $hit = $memo ? (self::$ruleMemo[$key] ?? null) : null;
        if ($hit !== null && $hit[0] > $now) {
            return $hit[1];
        }

        self::ensureRulesTable();

        $decoded = self::decodeStoredRule(
            DB::table('amazon_ads_rules')->where('key', $key)->value('rule')
        );
        if ($memo) {
            self::$ruleMemo[$key] = [$now + self::$ruleMemoSeconds, $decoded];
        }

        return $decoded;
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
        unset(self::$ruleMemo[$key]);
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
        if (self::$rulesTableKnown && self::ruleMemoOn()) {
            return;
        }
        if (SchemaMemo::hasTable('amazon_ads_rules')) {
            self::$rulesTableKnown = true;

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
        self::$rulesTableKnown = true;
    }
}
