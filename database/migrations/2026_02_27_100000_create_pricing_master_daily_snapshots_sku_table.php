<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pricing_master_daily_snapshots_sku', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->index();
            $table->string('sku', 255)->index();
            $table->unsignedBigInteger('inventory')->default(0);
            $table->unsignedBigInteger('overall_l30')->default(0);
            $table->decimal('avg_price', 12, 2)->nullable();
            $table->decimal('avg_cvr', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['snapshot_date', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_master_daily_snapshots_sku');
    }
};
