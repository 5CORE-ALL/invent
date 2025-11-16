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
        Schema::create('kw_amazon', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique()->index();
            $table->boolean('ra')->default(false);
            $table->boolean('nra')->default(false);
            $table->boolean('running')->default(false);
            $table->boolean('to_pause')->default(false);
            $table->boolean('paused')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kw_amazon');
    }
};
