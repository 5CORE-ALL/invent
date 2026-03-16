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
        if (!Schema::hasTable('movement_analysis')) {
            Schema::create('movement_analysis', function (Blueprint $table) {
                $table->id();
                $table->string('parent')->nullable();
                $table->string('sku')->index();
                $table->longText('months')->nullable(); // JSON field to store monthly data
                $table->integer('s_msl')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movement_analysis');
    }
};
