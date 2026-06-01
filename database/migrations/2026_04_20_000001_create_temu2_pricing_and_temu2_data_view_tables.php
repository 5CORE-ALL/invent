<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Temu 2: same schema as temu_pricing / temu_data_view, separate tables.
     */
    public function up(): void
    {
        if (!Schema::hasTable('temu2_pricing')) {
            Schema::create('temu2_pricing', function (Blueprint $table) {
                $table->id();
                $table->string('category')->nullable();
                $table->string('category_id')->nullable();
                $table->text('product_name')->nullable();
                $table->string('contribution_goods')->nullable();
                $table->string('sku')->index();
                $table->string('goods_id')->nullable();
                $table->string('sku_id')->nullable();
                $table->string('variation')->nullable();
                $table->integer('quantity')->default(0);
                $table->decimal('base_price', 10, 2)->nullable();
                $table->string('external_product_id_type')->nullable();
                $table->string('external_product_id')->nullable();
                $table->string('status')->nullable();
                $table->string('detail_status')->nullable();
                $table->timestamp('date_created')->nullable();
                $table->text('incomplete_product_information')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('temu2_data_view')) {
            Schema::create('temu2_data_view', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->unique();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('temu2_data_view');
        Schema::dropIfExists('temu2_pricing');
    }
};
