<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ads_link_sku_fields')) {
            return;
        }

        Schema::create('ads_link_sku_fields', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('sku_norm')->unique();
            $table->json('plus_kw')->nullable();
            $table->json('minus_kw')->nullable();
            $table->json('plus_pt')->nullable();
            $table->json('minus_pt')->nullable();
            $table->json('plus_kw_spl')->nullable();
            $table->json('pt_spl')->nullable();
            $table->json('spl_minus_kw')->nullable();
            $table->json('spl_minus_pt')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_link_sku_fields');
    }
};
