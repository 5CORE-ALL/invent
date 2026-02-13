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
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->integer('old_qty')->nullable();
            $table->integer('new_qty')->nullable();
            $table->integer('qty_change')->nullable();
            $table->string('change_source'); // csv_import, manual_adjustment, api_sync, etc.
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('pushed_to_shopify')->default(false);
            $table->timestamp('shopify_pushed_at')->nullable();
            $table->text('shopify_error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('change_source');
            $table->index('batch_id');
            $table->index('pushed_to_shopify');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
    }
};
