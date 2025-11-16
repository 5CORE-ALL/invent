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
        Schema::create('product_master', function (Blueprint $table) {
            $table->id();
            $table->string('image')->nullable();;
            $table->string('category');
            $table->string('parent')->nullable();
            $table->string('sku')->unique();
            $table->enum('unit', ['pcs', 'pair']);
            $table->decimal('lp', 10, 2)->nullable();
            $table->decimal('cp', 10, 2)->nullable();
            $table->decimal('frght', 10, 2)->nullable();
            $table->decimal('ship', 10, 2)->nullable();
            $table->integer('label_qty')->nullable();
            $table->integer('lps')->nullable();
            $table->decimal('wt_act', 10, 2)->nullable();
            $table->decimal('wt_decl', 10, 2)->nullable();
            $table->decimal('l1', 10, 2)->nullable();
            $table->decimal('w1', 10, 2)->nullable();
            $table->decimal('h1', 10, 2)->nullable();
            $table->decimal('l2', 10, 2)->nullable();
            $table->decimal('w2', 10, 2)->nullable();
            $table->decimal('h2', 10, 2)->nullable();
            $table->decimal('cbm_item', 10, 4)->nullable();
            $table->decimal('cbm_carton', 10, 4)->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->integer('pcs_per_box')->nullable();
            $table->enum('status', ['Active', 'DC', '2BDC', 'Sourcing', 'In Transit', 'To Order', 'MFRG'])->default('Active');
            $table->string('item_link')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_master');
    }
};
