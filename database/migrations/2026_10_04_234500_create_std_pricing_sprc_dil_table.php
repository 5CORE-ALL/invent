<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('std_pricing_sprc_dil')) {
            return;
        }

        Schema::create('std_pricing_sprc_dil', function (Blueprint $table) {
            $table->id();
            $table->json('rules');
            $table->json('cvr_adj')->nullable();
            $table->decimal('clearance_nroi', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('std_pricing_sprc_dil');
    }
};
