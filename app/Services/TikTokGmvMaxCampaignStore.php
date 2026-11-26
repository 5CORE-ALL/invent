<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign-level GMV Max numbers (same columns as the Ads Manager export:
 * campaign, budget, cost, SKU orders, cost per order, gross revenue, ROI).
 * Filled by the GitHub Actions job through TikTokGmvMaxIngestController::campaigns().
 */
class TikTokGmvMaxCampaignStore
{
    public const TABLE = 'tiktok_gmv_max_campaigns';

    public const RANGES = ['L30', 'L7', 'L1'];

    public static function ensureTable(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            if (! Schema::hasColumn(self::TABLE, 'item_group_ids')) {
                Schema::table(self::TABLE, function ($t) {
                    $t->mediumText('item_group_ids')->nullable();
                });
            }

            return;
        }
        Schema::create(self::TABLE, function ($t) {
            $t->id();
            $t->string('campaign_id', 40);
            $t->string('report_range', 8);
            $t->string('campaign_name')->nullable();
            $t->string('promotion_type', 40)->nullable();
            $t->string('status', 40)->nullable();
            $t->decimal('budget', 14, 2)->nullable();
            $t->decimal('cost', 14, 2)->default(0);
            $t->unsignedInteger('sku_orders')->default(0);
            $t->decimal('cost_per_order', 14, 2)->nullable();
            $t->decimal('gross_revenue', 14, 2)->default(0);
            $t->decimal('roi', 10, 2)->nullable();
            $t->string('currency', 8)->default('USD');
            $t->date('period_start')->nullable();
            $t->date('period_end')->nullable();
            $t->mediumText('item_group_ids')->nullable();
            $t->timestamp('fetched_at')->nullable();
            $t->timestamps();
            $t->unique(['campaign_id', 'report_range']);
            $t->index('report_range');
        });
    }

    /**
     * Replace every row of one range with the freshly uploaded campaigns.
     *
     * @param  list<array<string, mixed>>  $campaigns
     */
    public static function replaceRange(string $range, ?string $start, ?string $end, array $campaigns): int
    {
        self::ensureTable();
        $now = now();
        $rows = [];
        foreach ($campaigns as $c) {
            $id = trim((string) ($c['campaign_id'] ?? ''));
            if ($id === '' || ! preg_match('/^\d{5,25}$/', $id)) {
                continue;
            }
            $cost = round((float) ($c['cost'] ?? 0), 2);
            $orders = (int) round((float) ($c['orders'] ?? 0));
            $revenue = round((float) ($c['gross_revenue'] ?? 0), 2);
            $roi = isset($c['roi']) && $c['roi'] !== '' && $c['roi'] !== null
                ? round((float) $c['roi'], 2)
                : ($cost > 0 ? round($revenue / $cost, 2) : null);
            $rows[$id] = [
                'campaign_id' => $id,
                'report_range' => $range,
                'campaign_name' => isset($c['campaign_name']) ? mb_substr((string) $c['campaign_name'], 0, 250) : null,
                'promotion_type' => isset($c['promotion_type']) ? mb_substr((string) $c['promotion_type'], 0, 40) : null,
                'status' => isset($c['status']) ? mb_substr((string) $c['status'], 0, 40) : null,
                'budget' => isset($c['budget']) && $c['budget'] !== '' && $c['budget'] !== null ? round((float) $c['budget'], 2) : null,
                'cost' => $cost,
                'sku_orders' => $orders,
                'cost_per_order' => $orders > 0 ? round($cost / $orders, 2) : null,
                'gross_revenue' => $revenue,
                'roi' => $roi,
                'currency' => 'USD',
                'item_group_ids' => self::encodeIds($c['item_group_ids'] ?? null),
                'period_start' => $start,
                'period_end' => $end,
                'fetched_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($range, $rows) {
            DB::table(self::TABLE)->where('report_range', $range)->delete();
            foreach (array_chunk(array_values($rows), 200) as $chunk) {
                DB::table(self::TABLE)->insert($chunk);
            }
        });

        return count($rows);
    }

    /**
     * @param  mixed  $ids
     */
    private static function encodeIds($ids): ?string
    {
        if (! is_array($ids)) {
            return null;
        }
        $clean = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id !== '' && preg_match('/^\d{5,25}$/', $id)) {
                $clean[$id] = $id;
            }
        }

        return $clean ? json_encode(array_values($clean)) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(string $range): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }

        return DB::table(self::TABLE)
            ->where('report_range', $range)
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
