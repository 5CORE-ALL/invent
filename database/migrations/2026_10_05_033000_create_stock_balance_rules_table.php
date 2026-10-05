<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_balance_rules')) {
            return;
        }

        Schema::create('stock_balance_rules', function (Blueprint $table) {
            $table->id();
            $table->string('to_sku')->unique();
            $table->string('from_sku')->nullable();
            $table->string('ratio', 20)->default('1:1');
            $table->unsignedInteger('from_qty')->nullable();
            $table->string('action', 10)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balance_rules');
    }
};
