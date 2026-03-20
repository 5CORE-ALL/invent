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
        if(!Schema::hasTable('china_load')) {
            Schema::create('china_load', function (Blueprint $table) {
                $table->id(); // auto increment
                $table->unsignedBigInteger('container_sl_no')->default(66); // start from 66 manually
                $table->string('load')->nullable();
                $table->text('list_of_goods')->nullable();
                $table->string('shut_out')->nullable();
                $table->string('obl')->nullable();
                $table->string('mbl')->nullable();
                $table->string('container_no')->nullable();
                $table->string('item')->nullable();
                $table->string('cha_china')->nullable();
                $table->string('consignee')->nullable();
                $table->string('status', 20)->nullable();
                // No timestamps if not needed
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('china_load');
    }
};
