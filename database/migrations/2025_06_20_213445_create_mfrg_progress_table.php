<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (!Schema::hasTable('mfrg_progress')) {
            Schema::create('mfrg_progress', function (Blueprint $table) {
                $table->id();
                $table->string('parent')->nullable();
                $table->string('sku')->nullable();
                $table->integer('qty')->nullable();
                $table->string('rate_currency', 20)->nullable();
                $table->decimal('rate', 10, 2)->nullable();
                $table->string('supplier')->nullable();
                $table->decimal('advance_amt', 10, 2)->nullable();
                $table->date('adv_date')->nullable();
                $table->date('pay_conf_date')->nullable();
                $table->date('del_date')->nullable();
                $table->string('o_links')->nullable();
                $table->decimal('value', 12, 2)->nullable();
                $table->decimal('payment_pending', 12, 2)->nullable();
                $table->string('photo_packing')->nullable();
                $table->string('photo_int_sale')->nullable();
                $table->integer('total_cbm')->nullable();
                $table->string('barcode_sku')->nullable();
                $table->string('artwork_manual_book')->nullable();
                $table->text('notes')->nullable();
                $table->string('ready_to_ship', 20)->default(false);
                $table->timestamps();
            });
        }
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mfrg_progress');
    }
};
