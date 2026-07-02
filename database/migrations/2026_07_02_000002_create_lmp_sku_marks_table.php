<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lmp_sku_marks')) {
            return;
        }

        Schema::create('lmp_sku_marks', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('sku_norm')->unique();
            $table->string('m', 1)->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->index('sku_norm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lmp_sku_marks');
    }
};
