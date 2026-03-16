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
        if (!Schema::hasTable('ready_to_ship')) {
            Schema::create('ready_to_ship', function (Blueprint $table) {
                $table->id();
                $table->string('parent')->nullable();
                $table->string('sku')->nullable();
                $table->integer('qty')->nullable();
                $table->integer('rate')->nullable();
                $table->string('supplier')->nullable();
                $table->string('cbm',100)->nullable();
                $table->string('area')->nullable();
                $table->float('shipd_cbm_in_cont')->nullable();
                $table->string('payment')->nullable();
                $table->string('payment_confirmation')->nullable();
                $table->string('model_number')->nullable();
                $table->string('photo_mail_send')->nullable();
                $table->string('followup_delivery')->nullable();
                $table->string('packing_list')->nullable();
                $table->string('container_rfq')->nullable();
                $table->string('quote_result')->nullable();
                $table->string('pay_term')->nullable();
                $table->integer('transit_inv_status')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ready_to_ship');
    }
};
