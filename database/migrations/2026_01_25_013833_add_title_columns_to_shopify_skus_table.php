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
        if (! Schema::hasTable('shopify_skus')) {
            return;
        }

        if (! Schema::hasColumn('shopify_skus', 'product_title')) {
            Schema::table('shopify_skus', function (Blueprint $table) {
                $table->text('product_title')->nullable()->after('sku');
            });
        }
        if (! Schema::hasColumn('shopify_skus', 'variant_title')) {
            Schema::table('shopify_skus', function (Blueprint $table) {
                $table->text('variant_title')->nullable()->after('product_title');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('shopify_skus')) {
            return;
        }

        $columns = ['product_title', 'variant_title'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('shopify_skus', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('shopify_skus', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
