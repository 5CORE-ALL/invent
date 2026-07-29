<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amazon_sprice_cvr_daily_stats')) {
            return;
        }

        Schema::create('amazon_sprice_cvr_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->unsignedInteger('red_count')->default(0);
            $table->unsignedInteger('green_count')->default(0);
            $table->unsignedInteger('pink_count')->default(0);
            $table->unsignedInteger('increased_count')->default(0);
            $table->unsignedInteger('decreased_count')->default(0);
            $table->unsignedInteger('hold_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->decimal('low_cvr', 8, 2)->nullable();
            $table->decimal('high_cvr', 8, 2)->nullable();
            $table->timestamps();

            $table->unique('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_sprice_cvr_daily_stats');
    }
};
