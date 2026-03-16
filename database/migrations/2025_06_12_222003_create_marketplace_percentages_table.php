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
        Schema::create('marketplace_percentages', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace');
            $table->decimal('percentage', 8, 2);
            $table->timestamps();

            // Add unique constraint on marketplace
            $table->unique('marketplace');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_percentages');
    }
};
