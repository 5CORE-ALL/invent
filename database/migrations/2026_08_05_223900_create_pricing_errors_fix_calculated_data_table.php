<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-calculated Pricing Errors Fix rows (SKU × marketplace).
 * Built by: php artisan pricing-errors:calculate-data
 * Served by: GET /pricing-errors-fix-data-json (fast path)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_errors_fix_calculated_data', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->index();
            $table->string('marketplace', 64)->index();
            $table->string('pull_key', 64)->nullable()->index();
            $table->string('channel_label', 64)->nullable();
            $table->string('parent', 191)->nullable()->index();
            $table->string('image_path', 512)->nullable();
            $table->decimal('inv', 12, 2)->default(0);
            $table->decimal('ov_l30', 12, 2)->default(0);
            $table->decimal('dil', 10, 2)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('groi', 12, 2)->nullable();
            $table->decimal('nroi', 12, 2)->nullable();
            $table->decimal('gpft', 12, 2)->nullable();
            $table->decimal('npft', 12, 2)->nullable();
            $table->decimal('sprice', 12, 2)->nullable();
            $table->decimal('sroi', 12, 2)->nullable(); // SGROI (gross)
            $table->decimal('sgpft', 12, 2)->nullable();
            $table->decimal('snroi', 12, 2)->nullable(); // SROI (net)
            $table->decimal('snpft', 12, 2)->nullable();
            $table->string('success', 64)->nullable();
            $table->decimal('lp', 12, 2)->default(0);
            $table->decimal('ship', 12, 2)->default(0);
            $table->decimal('margin', 8, 4)->default(0);
            $table->decimal('ads_pct', 8, 4)->default(0);
            $table->timestamp('calculated_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['sku', 'marketplace'], 'pef_sku_marketplace_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_errors_fix_calculated_data');
    }
};
