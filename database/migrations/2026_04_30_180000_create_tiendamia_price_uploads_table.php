<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rows from Mirakl/Tiendamia tab-separated offer export (see teinda.txt header).
     */
    public function up(): void
    {
        if (Schema::hasTable('tiendamia_price_uploads')) {
            return;
        }

        Schema::create('tiendamia_price_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('upload_batch_id')->index();
            $table->string('source_filename')->nullable();
            $table->unsignedInteger('row_index')->nullable();

            $table->string('offer_sku')->index();
            $table->string('product_sku', 64)->nullable();
            $table->string('category_code', 32)->nullable();
            $table->string('category_label')->nullable();
            $table->string('brand')->nullable();
            $table->longText('product')->nullable();
            $table->string('offer_state', 64)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('original_price', 12, 2)->nullable();
            $table->integer('quantity')->nullable();
            $table->string('alert_threshold', 64)->nullable();
            $table->string('logistic_class', 64)->nullable();
            $table->string('activated', 16)->nullable();
            $table->string('available_start_date', 128)->nullable();
            $table->string('available_end_date', 128)->nullable();
            $table->decimal('discount_price', 12, 2)->nullable();
            $table->string('discount_start_date', 128)->nullable();
            $table->string('discount_end_date', 128)->nullable();
            $table->text('ean')->nullable();
            $table->string('inactivity_reason')->nullable();
            $table->string('fulfillment_center_code', 64)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiendamia_price_uploads');
    }
};
