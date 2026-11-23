<?php

namespace App\Services\Lqs;

use App\Models\LqsMarketplaceAction;
use App\Models\LqsShopifySeoScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers each time a Shopify listing's Yoast colour goes from red (needs improvement) to green (good).
 * The LQS Shopify page draws these, day by day, next to how many Action Taken notes were saved.
 */
class LqsShopifyRatingMoves
{
    public const TABLE = 'lqs_shopify_rating_moves';

    public static function ensureTable(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }
        Schema::create(self::TABLE, function ($table) {
            $table->id();
            $table->unsignedBigInteger('shopify_product_id')->index();
            $table->string('sku', 191)->nullable();
            $table->string('kind', 16);
            $table->string('from_rating', 16);
            $table->string('to_rating', 16);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Called while a product is re-scored. A row is written only for red → green.
     *
     * @param  array{seo_rating?: string, readability_rating?: string}  $scored
     * @param  array<string, mixed>  $product
     */
    public static function record(?LqsShopifySeoScore $before, array $scored, array $product): void
    {
        if ($before === null) {
            return;
        }
        self::ensureTable();
        $sku = '';
        foreach ((array) ($product['skus'] ?? $before->skus ?? []) as $one) {
            $one = trim((string) $one);
            if ($one !== '') {
                $sku = $one;
                break;
            }
        }
        $now = now();
        foreach (['seo' => 'seo_rating', 'readability' => 'readability_rating'] as $kind => $column) {
            $from = strtolower(trim((string) ($before->{$column} ?? '')));
            $to = strtolower(trim((string) ($scored[$column] ?? '')));
            if ($from !== 'bad' || $to !== 'good') {
                continue;
            }
            DB::table(self::TABLE)->insert([
                'shopify_product_id' => (int) $before->shopify_product_id,
                'sku' => $sku !== '' ? $sku : null,
                'kind' => $kind,
                'from_rating' => $from,
                'to_rating' => $to,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * @return array{days: list<array{date: string, label: string, red_to_green: int, actions: int}>, recent: list<array<string, mixed>>, totals: array<string, int>}
     */
    public static function pulse(int $days = 14): array
    {
        self::ensureTable();
        $days = max(7, min(60, $days));
        $start = now()->startOfDay()->subDays($days - 1);

        $moves = DB::table(self::TABLE)
            ->where('created_at', '>=', $start)
            ->get(['sku', 'kind', 'created_at']);

        $actions = Schema::hasTable('lqs_marketplace_actions')
            ? LqsMarketplaceAction::query()
                ->where('marketplace', 'shopify')
                ->where('created_at', '>=', $start)
                ->with('user:id,name')
                ->orderBy('created_at')
                ->get(['id', 'sku', 'action', 'user_id', 'created_at'])
            : collect();

        $actionsOnSku = Schema::hasTable('lqs_marketplace_actions')
            ? LqsMarketplaceAction::query()
                ->where('marketplace', 'shopify')
                ->selectRaw('sku, COUNT(*) as n')
                ->groupBy('sku')
                ->pluck('n', 'sku')
            : collect();

        $byDay = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $byDay[$day->toDateString()] = [
                'date' => $day->toDateString(),
                'label' => $day->format('d M'),
                'red_to_green' => 0,
                'actions' => 0,
                'details' => [],
                'more' => 0,
            ];
        }
        foreach ($moves as $row) {
            $key = substr((string) $row->created_at, 0, 10);
            if (isset($byDay[$key])) {
                $byDay[$key]['red_to_green']++;
            }
        }
        foreach ($actions as $row) {
            $key = $row->created_at?->toDateString();
            if ($key === null || ! isset($byDay[$key])) {
                continue;
            }
            $byDay[$key]['actions']++;
            if (count($byDay[$key]['details']) >= 8) {
                $byDay[$key]['more']++;

                continue;
            }
            $sku = trim((string) $row->sku);
            $byDay[$key]['details'][] = [
                'sku' => $sku,
                'user' => (string) ($row->user->name ?? 'Unknown'),
                'action' => (string) $row->action,
                'sku_actions' => (int) ($actionsOnSku[$sku] ?? 1),
            ];
        }

        $recent = DB::table(self::TABLE)
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'sku' => (string) ($row->sku ?: 'product '.$row->shopify_product_id),
                'kind' => $row->kind === 'readability' ? 'Readability' : 'SEO',
                'at' => (string) $row->created_at,
            ])
            ->all();

        return [
            'days' => array_values($byDay),
            'recent' => $recent,
            'totals' => [
                'red_to_green' => (int) DB::table(self::TABLE)->count(),
                'red_to_green_window' => $moves->count(),
                'actions_window' => $actions->count(),
            ],
        ];
    }
}
