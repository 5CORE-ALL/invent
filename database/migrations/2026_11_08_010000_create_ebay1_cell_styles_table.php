<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ebay1 Data: text colour and background colour of single cells (like Excel), shared by every user.
     * One row per SKU + column (field). A row with neither colour is deleted.
     */
    public function up(): void
    {
        if (Schema::hasTable('ebay1_cell_styles')) {
            return;
        }

        Schema::create('ebay1_cell_styles', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191);
            $table->string('field', 100);
            $table->string('color', 9)->nullable();
            $table->string('bg', 9)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['sku', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay1_cell_styles');
    }
};
