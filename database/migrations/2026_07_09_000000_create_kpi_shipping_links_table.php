<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kpi_shipping_links')) {
            return;
        }

        Schema::create('kpi_shipping_links', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 191)->unique();
            $table->string('link', 2048)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_shipping_links');
    }
};
