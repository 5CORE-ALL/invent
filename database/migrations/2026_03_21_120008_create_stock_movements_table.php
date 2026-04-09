<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_movements')) {
            return;
        }

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('product_master')->cascadeOnDelete();
            $table->string('sku', 191)->index();
            $table->foreignId('from_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->foreignId('to_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->unsignedInteger('qty');
            $table->string('type', 32)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('inventory_id')->nullable()->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
