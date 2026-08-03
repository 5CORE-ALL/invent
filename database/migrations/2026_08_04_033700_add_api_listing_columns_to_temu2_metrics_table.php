<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('temu2_metrics')) {
            return;
        }

        Schema::table('temu2_metrics', function (Blueprint $table) {
            if (! Schema::hasColumn('temu2_metrics', 'base_price')) {
                $table->decimal('base_price', 12, 2)->nullable()->after('goods_id');
            }
            if (! Schema::hasColumn('temu2_metrics', 'quantity')) {
                $table->integer('quantity')->nullable()->after('base_price');
            }
            if (! Schema::hasColumn('temu2_metrics', 'quantity_purchased_l30')) {
                $table->integer('quantity_purchased_l30')->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('temu2_metrics', 'quantity_purchased_l60')) {
                $table->integer('quantity_purchased_l60')->nullable()->after('quantity_purchased_l30');
            }
            if (! Schema::hasColumn('temu2_metrics', 'recommended_base_price')) {
                $table->decimal('recommended_base_price', 12, 2)->nullable()->after('quantity_purchased_l60');
            }
            if (! Schema::hasColumn('temu2_metrics', 'product_impressions_l30')) {
                $table->unsignedBigInteger('product_impressions_l30')->nullable()->after('recommended_base_price');
            }
            if (! Schema::hasColumn('temu2_metrics', 'product_clicks_l30')) {
                $table->unsignedBigInteger('product_clicks_l30')->nullable()->after('product_impressions_l30');
            }
            if (! Schema::hasColumn('temu2_metrics', 'product_impressions_l60')) {
                $table->unsignedBigInteger('product_impressions_l60')->nullable()->after('product_clicks_l30');
            }
            if (! Schema::hasColumn('temu2_metrics', 'product_clicks_l60')) {
                $table->unsignedBigInteger('product_clicks_l60')->nullable()->after('product_impressions_l60');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('temu2_metrics')) {
            return;
        }

        Schema::table('temu2_metrics', function (Blueprint $table) {
            foreach ([
                'base_price',
                'quantity',
                'quantity_purchased_l30',
                'quantity_purchased_l60',
                'recommended_base_price',
                'product_impressions_l30',
                'product_clicks_l30',
                'product_impressions_l60',
                'product_clicks_l60',
            ] as $column) {
                if (Schema::hasColumn('temu2_metrics', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
