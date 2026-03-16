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
        // Drop linked_products_data table if it exists
        Schema::dropIfExists('linked_products_data');

        // Remove group_id column from product_master table
        Schema::table('product_master', function (Blueprint $table) {
            if (Schema::hasColumn('product_master', 'group_id')) {
                $table->dropColumn('group_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate linked_products_data table
        Schema::create('linked_products_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('sku')->nullable();
            $table->integer('old_qty')->nullable();
            $table->integer('new_qty')->nullable();
            $table->decimal('old_dil', 8, 2)->nullable();
            $table->decimal('new_dil', 8, 2)->nullable();
            $table->timestamps();
            
            $table->index('group_id');
            $table->index('sku');
        });

        // Add group_id column back to product_master table
        Schema::table('product_master', function (Blueprint $table) {
            if (!Schema::hasColumn('product_master', 'group_id')) {
                $table->unsignedBigInteger('group_id')->nullable()->after('parent');
                $table->index('group_id');
            }
        });
    }
};
