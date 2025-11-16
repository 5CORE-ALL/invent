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
        if (Schema::hasTable('product_master')) {
            $columnsToDrop = [
                'image', 'category', 'unit', 'lp', 'cp', 'frght', 'ship',
                'label_qty', 'lps', 'wt_act', 'wt_decl', 'l1', 'w1', 'h1',
                'l2', 'w2', 'h2', 'cbm_item', 'cbm_carton', 'weight',
                'pcs_per_box', 'status', 'item_link'
            ];
            $existingColumns = array_filter($columnsToDrop, function($col) {
                return Schema::hasColumn('product_master', $col);
            });
            if (!empty($existingColumns)) {
                Schema::table('product_master', function (Blueprint $table) use ($existingColumns) {
                    $table->dropColumn($existingColumns);
                });
            }

            // Add 'Values' column if it doesn't exist
            if (!Schema::hasColumn('product_master', 'Values')) {
                Schema::table('product_master', function (Blueprint $table) {
                    $table->json('Values')->nullable()->after('sku');
                });
            }

            // Add soft deletes if not present
            if (!Schema::hasColumn('product_master', 'deleted_at')) {
                Schema::table('product_master', function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            $table->dropColumn(['Values', 'deleted_at']);
            // You may want to re-add dropped columns here if needed
        });
    }
};
