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
        if (!Schema::hasTable('on_sea_transit')) {
            Schema::create('on_sea_transit', function (Blueprint $table) {
                $table->id();
                $table->integer('container_sl_no');
                $table->string('bl_check')->nullable();
                $table->string('bl_link')->nullable();
                $table->string('isf')->nullable();
                $table->date('etd')->nullable();
                $table->string('port_arrival', 20)->nullable();
                $table->date('eta_date_ohio')->nullable();
                $table->string('status')->nullable();
                $table->string('isf_usa_agent', 50)->nullable();
                $table->string('duty_calcu', 50)->nullable();
                $table->string('invoice_send_to_dominic', 50)->nullable();
                $table->string('arrival_notice_email', 50)->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('on_sea_transit');
    }
};
