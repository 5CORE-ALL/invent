<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spare_part_details')) {
            return;
        }

        Schema::create('spare_part_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_master_id')->unique()->constrained('product_master')->cascadeOnDelete();
            $table->string('part_name', 255);
            $table->string('msl_part', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spare_part_details');
    }
};
