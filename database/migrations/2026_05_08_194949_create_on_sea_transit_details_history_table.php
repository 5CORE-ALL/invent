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
        Schema::create('on_sea_transit_details_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('on_sea_transit_id');
            $table->integer('container_sl_no');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            
            $table->foreign('on_sea_transit_id')->references('id')->on('on_sea_transit')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('on_sea_transit_details_history');
    }
};
