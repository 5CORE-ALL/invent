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
        Schema::table('ebay_metrics', function (Blueprint $table) {
            $table->decimal('total_pft', 12, 2)->nullable()->after('ebay_price');
            $table->decimal('t_sale_l30', 12, 2)->nullable()->after('total_pft');
            $table->decimal('pft_percentage', 8, 2)->nullable()->after('t_sale_l30');
            $table->decimal('roi_percentage', 8, 2)->nullable()->after('pft_percentage');
            $table->decimal('t_cogs', 12, 2)->nullable()->after('roi_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('ebay_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'total_pft',
                't_sale_l30',
                'pft_percentage',
                'roi_percentage',
                't_cogs'
            ]);
        });
    }
};
