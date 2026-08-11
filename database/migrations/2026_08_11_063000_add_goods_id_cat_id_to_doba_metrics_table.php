<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doba_metrics', function (Blueprint $table) {
            if (! Schema::hasColumn('doba_metrics', 'goods_id')) {
                $table->string('goods_id')->nullable()->after('item_id');
            }
            if (! Schema::hasColumn('doba_metrics', 'cat_id')) {
                $table->string('cat_id')->nullable()->after('goods_id');
            }
        });

        // Backfill from manually saved seller links:
        // https://seller.doba.com/ds/goods/save?goodsId=...&catId=...
        if (Schema::hasTable('doba_listing_statuses')) {
            $rows = DB::table('doba_listing_statuses')->select('sku', 'value')->get();
            foreach ($rows as $row) {
                $value = is_array($row->value)
                    ? $row->value
                    : (json_decode((string) $row->value, true) ?: []);
                $sellerLink = trim((string) ($value['seller_link'] ?? ''));
                if ($sellerLink === '') {
                    continue;
                }
                if (! preg_match('/[?&]goodsId=([^&]+)/i', $sellerLink, $goodsMatch)) {
                    continue;
                }
                if (! preg_match('/[?&]catId=([^&]+)/i', $sellerLink, $catMatch)) {
                    continue;
                }
                $goodsId = rawurldecode($goodsMatch[1]);
                $catId = rawurldecode($catMatch[1]);
                if ($goodsId === '' || $catId === '') {
                    continue;
                }

                DB::table('doba_metrics')
                    ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim((string) $row->sku))])
                    ->where(function ($q) {
                        $q->whereNull('goods_id')->orWhere('goods_id', '');
                    })
                    ->update([
                        'goods_id' => $goodsId,
                        'cat_id' => $catId,
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('doba_metrics', function (Blueprint $table) {
            if (Schema::hasColumn('doba_metrics', 'cat_id')) {
                $table->dropColumn('cat_id');
            }
            if (Schema::hasColumn('doba_metrics', 'goods_id')) {
                $table->dropColumn('goods_id');
            }
        });
    }
};
