<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fba_daily_metrics')) {
            return;
        }

        Schema::create('fba_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('record_date')->unique();
            $table->decimal('sales',    12, 2)->default(0);
            $table->decimal('pft',      12, 2)->default(0);
            $table->decimal('gpft',      8, 2)->default(0);  // avg GPFT %
            $table->decimal('price',     8, 2)->default(0);  // avg price $
            $table->decimal('cvr',       8, 2)->default(0);  // avg CVR %
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('inv')->default(0);   // total FBA INV
            $table->unsignedBigInteger('l30')->default(0);   // total L30
            $table->decimal('dil',       8, 2)->default(0);  // avg DIL %
            $table->unsignedInteger('zero_sold')->default(0);
            $table->decimal('ads_pct',   8, 2)->default(0);  // Spend/Sales %
            $table->decimal('spend',    12, 2)->default(0);
            $table->decimal('roi',       8, 2)->default(0);  // ROI %
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fba_daily_metrics');
    }
};
