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
        if(!Schema::hasTable('on_road_transit')) {
            Schema::create('on_road_transit', function (Blueprint $table) {
                $table->id();
                $table->integer('container_sl_no');
                $table->string('supplier_pay_against_bl', 50)->nullable();
                $table->string('cha_china_pay', 50)->nullable();
                $table->string('duty', 50)->nullable();
                $table->string('freight_due', 50)->nullable();
                $table->string('fwdr_usa_due', 50)->nullable();
                $table->string('cbp_form_7501', 50)->nullable();
                $table->string('transport_rfq', 50)->nullable();
                $table->string('freight_hold', 50)->nullable();
                $table->string('customs_hold', 50)->nullable();
                $table->string('pay_usa_cha', 50)->nullable();
                $table->string('inform_sam', 50)->nullable();
                $table->string('date_of_cont_return', 50)->nullable();
                $table->string('inv_verification', 50)->nullable();
                $table->string('qc_verification', 50)->nullable();
                $table->string('claims_if_any', 50)->nullable();
                $table->string('status', 50)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('on_road_transit');
    }
};
