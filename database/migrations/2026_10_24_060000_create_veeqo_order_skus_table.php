<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SKUs looked up from the Veeqo API by order number for orders no local table has
 * (found = false rows are re-checked after a week).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('veeqo_order_skus')) {
            return;
        }

        Schema::create('veeqo_order_skus', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 100)->unique();
            $table->unsignedBigInteger('veeqo_order_id')->nullable();
            $table->string('channel_name', 100)->nullable();
            $table->text('skus')->nullable();
            $table->boolean('found')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veeqo_order_skus');
    }
};
