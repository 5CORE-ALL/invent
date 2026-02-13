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
        Schema::create('inventory_import_errors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->integer('row_number');
            $table->string('sku')->nullable();
            $table->string('error_type'); // sku_not_found, shopify_push_failed, validation_error
            $table->text('error_message');
            $table->json('row_data')->nullable();
            $table->timestamps();

            $table->index('batch_id');
            $table->index('sku');
            $table->index('error_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_import_errors');
    }
};
