<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_fulfillment_trackings')) {
            return;
        }

        Schema::create('order_fulfillment_trackings', function (Blueprint $table) {
            $table->id();
            $table->string('row_key', 191);
            $table->string('mm_slug', 64);
            $table->string('order_id', 128)->nullable();
            $table->string('sku', 191)->nullable();
            $table->string('tracking_number', 128)->nullable();
            $table->string('carrier', 64)->nullable();
            $table->string('source', 32)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique('row_key', 'of_tracking_row_key_uq');
            $table->index(['mm_slug', 'order_id'], 'of_tracking_slug_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_fulfillment_trackings');
    }
};
