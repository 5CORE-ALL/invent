<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tiktok_sheet_data')) {
            return;
        }

        if (! Schema::hasColumn('tiktok_sheet_data', 'shopify_tiktok_price')) {
            $afterViews = Schema::hasColumn('tiktok_sheet_data', 'views');
            Schema::table('tiktok_sheet_data', function (Blueprint $table) use ($afterViews): void {
                if ($afterViews) {
                    $table->decimal('shopify_tiktok_price', 10, 2)->nullable()->after('views');
                } else {
                    $table->decimal('shopify_tiktok_price', 10, 2)->nullable();
                }
            });
        }
        if (! Schema::hasColumn('tiktok_sheet_data', 'shopify_tiktokl30')) {
            $afterPrice = Schema::hasColumn('tiktok_sheet_data', 'shopify_tiktok_price');
            Schema::table('tiktok_sheet_data', function (Blueprint $table) use ($afterPrice): void {
                if ($afterPrice) {
                    $table->integer('shopify_tiktokl30')->nullable()->after('shopify_tiktok_price');
                } else {
                    $table->integer('shopify_tiktokl30')->nullable();
                }
            });
        }
        if (! Schema::hasColumn('tiktok_sheet_data', 'shopify_tiktokl60')) {
            $afterL30 = Schema::hasColumn('tiktok_sheet_data', 'shopify_tiktokl30');
            Schema::table('tiktok_sheet_data', function (Blueprint $table) use ($afterL30): void {
                if ($afterL30) {
                    $table->integer('shopify_tiktokl60')->nullable()->after('shopify_tiktokl30');
                } else {
                    $table->integer('shopify_tiktokl60')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('tiktok_sheet_data')) {
            return;
        }

        $cols = array_values(array_filter(
            ['shopify_tiktok_price', 'shopify_tiktokl30', 'shopify_tiktokl60'],
            fn (string $c): bool => Schema::hasColumn('tiktok_sheet_data', $c)
        ));

        if ($cols === []) {
            return;
        }

        Schema::table('tiktok_sheet_data', function (Blueprint $table) use ($cols): void {
            $table->dropColumn($cols);
        });
    }
};