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
        Schema::create('lqs_history', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('total_inv', 10, 2)->default(0);
            $table->decimal('total_ov', 10, 2)->default(0);
            $table->decimal('avg_dil', 10, 2)->default(0);
            $table->decimal('avg_lqs', 10, 2)->default(0);
            $table->timestamps();

            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lqs_history');
    }
};
