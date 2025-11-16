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
        Schema::create('ebay_metrics', function (Blueprint $table) {
            $table->id();

            $table->string('item_id')->nullable();
            $table->string('sku')->nullable();

            $table->integer('ebay_l30')->nullable();
            $table->integer('ebay_l60')->nullable();
            $table->decimal('ebay_price', 10, 2)->nullable();

            $table->integer('kw_imp_l60')->nullable();
            $table->integer('kw_imp_l30')->nullable();
            $table->integer('kw_imp_l7')->nullable();

            $table->integer('kw_clk_l60')->nullable();
            $table->integer('kw_clk_l30')->nullable();
            $table->integer('kw_clk_l7')->nullable();

            $table->decimal('kw_spnd_l60', 10, 2)->nullable();
            $table->decimal('kw_spnd_l30', 10, 2)->nullable();
            $table->decimal('kw_spnd_l7', 10, 2)->nullable();
            $table->decimal('kw_spnd_l1', 10, 2)->nullable();

            $table->integer('kw_sld_l60')->nullable();
            $table->integer('kw_sld_l30')->nullable();
            $table->integer('kw_sld_l7')->nullable();

            $table->decimal('kw_sls_l60', 10, 2)->nullable();
            $table->decimal('kw_sls_l30', 10, 2)->nullable();
            $table->decimal('kw_sls_l7', 10, 2)->nullable();

            $table->decimal('kw_cpc_l60', 10, 4)->nullable();
            $table->decimal('kw_cpc_l30', 10, 4)->nullable();
            $table->decimal('kw_cpc_l7', 10, 4)->nullable();
            $table->decimal('kw_cpc_l1', 10, 4)->nullable();

            $table->integer('pmt_imp_l30')->nullable();
            $table->integer('pmt_imp_l7')->nullable();

            $table->integer('pmt_clk_l60')->nullable();
            $table->integer('pmt_clk_l30')->nullable();
            $table->integer('pmt_clk_l7')->nullable();

            $table->decimal('pmt_spnd_l30', 10, 2)->nullable();
            $table->decimal('pmt_spnd_l7', 10, 2)->nullable();

            $table->integer('pmt_sld_l30')->nullable();
            $table->integer('pmt_sld_l7')->nullable();

            $table->decimal('pmt_sls_l30', 10, 2)->nullable();
            $table->decimal('pmt_sls_l7', 10, 2)->nullable();

            $table->decimal('pmt_percent_rate', 5, 2)->nullable();

            $table->integer('oc_l30')->nullable();
            $table->integer('oc_l60')->nullable();

            $table->date('report_date')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_metrics');
    }
};
