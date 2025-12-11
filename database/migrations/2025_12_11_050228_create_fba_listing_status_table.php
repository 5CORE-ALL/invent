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
        Schema::create('fba_listing_status', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->json('status_value')->nullable()->comment('JSON value with options: All, FBA, FBM, NRL');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fba_listing_status');
    }
};
