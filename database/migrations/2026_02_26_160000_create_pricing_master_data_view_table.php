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
        if (Schema::hasTable('pricing_master_data_view')) {
            return;
        }

        Schema::create('pricing_master_data_view', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->string('marketplace', 64)->index();
            $table->decimal('sprice', 12, 2)->nullable();
            $table->decimal('amazon_margin', 8, 4)->nullable();
            $table->decimal('sgpft', 10, 2)->nullable();
            $table->decimal('spft', 10, 2)->nullable();
            $table->decimal('sroi', 10, 2)->nullable();
            $table->decimal('avg_pft', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['sku', 'marketplace']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_master_data_view');
    }
};
