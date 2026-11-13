<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restockings')) {
            return;
        }

        Schema::create('restockings', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable();
            $table->string('order_id', 100)->nullable()->index();
            $table->string('channel', 100)->nullable();
            $table->string('sku', 191)->nullable()->index();
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->decimal('refunded_amount', 12, 2)->nullable();
            $table->decimal('recovered_price', 12, 2)->nullable();
            $table->boolean('restocked')->default(false);
            $table->string('created_by', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restockings');
    }
};
