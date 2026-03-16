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
        Schema::create('ebay_2_metrics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('item_id')->index();
            $table->string('sku')->index();
            $table->decimal('ebay_price', 12, 2)->nullable();
            $table->integer('ebay_stock')->nullable();
            $table->string('listed_status')->nullable();
            $table->integer('ebay_l7')->nullable();
            $table->integer('ebay_l30')->nullable();
            $table->integer('ebay_l60')->nullable();
            $table->integer('views')->nullable();
            $table->integer('l7_views')->nullable();
            $table->date('report_range')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_2_metrics');
    }
};
