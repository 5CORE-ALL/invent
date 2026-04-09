<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('depop_sheet_data')) {
            return;
        }

        Schema::create('depop_sheet_data', function (Blueprint $table) {
            $table->id();
            $table->string('product_name')->nullable();
            $table->string('size')->nullable();
            $table->decimal('retail_price', 12, 2)->default(0);
            $table->integer('warehouse_stock')->default(0);
            $table->string('sku_code')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depop_sheet_data');
    }
};
