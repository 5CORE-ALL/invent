<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * vinted_pricing — the editable overlay for the Vinted Pricing page.
     *
     * Every row in the page is a ProductMaster SKU (LEFT JOINed). The editable
     * columns — `price`, `sprice`, and `l30` — live here so they survive
     * ProductMaster updates and so the import CSV can upsert them in one shot.
     */
    public function up(): void
    {
        if (Schema::hasTable('vinted_pricing')) {
            return;
        }

        Schema::create('vinted_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();              // matches product_master.sku
            $table->decimal('price', 12, 2)->nullable();  // user-entered Vinted list price
            $table->decimal('sprice', 12, 2)->nullable(); // editable SPRICE from pricing modes
            $table->integer('l30')->nullable();           // user-entered L30 units sold
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vinted_pricing');
    }
};
