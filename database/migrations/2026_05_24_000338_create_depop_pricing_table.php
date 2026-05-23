<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * depop_pricing — the editable overlay for the Depop Pricing page.
     *
     * Every row in the page is a ProductMaster SKU (LEFT JOINed). The two
     * editable columns — `price` and `l30` — live here so they survive
     * ProductMaster updates and so the import CSV can upsert them in one shot.
     */
    public function up(): void
    {
        if (Schema::hasTable('depop_pricing')) {
            return;
        }

        Schema::create('depop_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();              // matches product_master.sku
            $table->decimal('price', 12, 2)->nullable();  // user-entered Depop list price
            $table->integer('l30')->nullable();           // user-entered L30 units sold
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depop_pricing');
    }
};
