<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('topdawg_order_metrics')) {
            return;
        }

        Schema::create('topdawg_order_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('order_date')->nullable();
            $table->timestamp('order_paid_at')->nullable();
            $table->string('status', 64)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('display_sku')->nullable();
            $table->string('sku', 255)->nullable();
            $table->integer('quantity')->default(1);
            $table->string('order_number')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topdawg_order_metrics');
    }
};
