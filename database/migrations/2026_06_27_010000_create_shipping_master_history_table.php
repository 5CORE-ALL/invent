<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipping_master_history')) {
            return;
        }

        Schema::create('shipping_master_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('sku')->index();
            $table->string('field', 64);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('updated_at')->useCurrent();
            $table->index(['product_id', 'updated_at']);
            $table->index(['sku', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_master_history');
    }
};
