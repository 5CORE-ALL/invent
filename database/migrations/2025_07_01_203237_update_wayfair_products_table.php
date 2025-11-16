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
        Schema::table('wayfair_products', function (Blueprint $table) {
            // Remove old column
            $table->dropColumn('supplier_data');

            // Add new columns
            $table->string('sku')->after('id');
            $table->string('po_number')->after('sku');
        });

        // Check for duplicates after columns are added
        $duplicates = \DB::table('wayfair_products')
            ->select('sku', 'po_number', \DB::raw('COUNT(*) as count'))
            ->groupBy('sku', 'po_number')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->count() > 0) {
            info('Duplicate (sku, po_number) pairs found in wayfair_products table:');
            foreach ($duplicates as $dup) {
                info("sku: {$dup->sku}, po_number: {$dup->po_number}, count: {$dup->count}");
            }
            throw new Exception('Migration aborted: Duplicate (sku, po_number) pairs exist in wayfair_products table. Please resolve them before running this migration.');
        }

        Schema::table('wayfair_products', function (Blueprint $table) {
            // Add constraints/indexes
            $table->unique(['sku', 'po_number']);
            $table->index('sku');
            $table->index('po_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wayfair_products', function (Blueprint $table) {
            $table->dropUnique(['wayfair_products_sku_po_number_unique']);
            $table->dropIndex(['sku']);
            $table->dropIndex(['po_number']);

            $table->dropColumn('sku');
            $table->dropColumn('po_number');

            $table->json('supplier_data')->nullable();
        });
    }
};
