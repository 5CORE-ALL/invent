<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('depop_sales_data')) {
            return;
        }

        Schema::create('depop_sales_data', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date')->nullable()->index();
            $table->string('buyer')->nullable();
            $table->text('description')->nullable();
            $table->string('size')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('item_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('usps_cost', 12, 2)->nullable();
            $table->decimal('depop_fee', 12, 2)->nullable();
            $table->string('sku_code')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depop_sales_data');
    }
};
