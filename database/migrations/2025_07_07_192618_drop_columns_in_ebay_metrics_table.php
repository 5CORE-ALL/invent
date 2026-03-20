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
            $table->dropColumn('kw_imp_l60');
            $table->dropColumn('kw_imp_l30');
            $table->dropColumn('kw_imp_l7');
            $table->dropColumn('kw_clk_l60');
            $table->dropColumn('kw_clk_l30');
            $table->dropColumn('kw_clk_l7');
            $table->dropColumn('kw_spnd_l60');
            $table->dropColumn('kw_spnd_l30');
            $table->dropColumn('kw_spnd_l7');
            $table->dropColumn('kw_spnd_l1');
            $table->dropColumn('kw_sld_l60');
            $table->dropColumn('kw_sld_l30');
            $table->dropColumn('kw_sld_l7');
            $table->dropColumn('kw_sls_l60');
            $table->dropColumn('kw_sls_l30');
            $table->dropColumn('kw_sls_l7');
            $table->dropColumn('kw_cpc_l60');
            $table->dropColumn('kw_cpc_l30');
            $table->dropColumn('kw_cpc_l7');
            $table->dropColumn('kw_cpc_l1');
            $table->dropColumn('pmt_imp_l30');
            $table->dropColumn('pmt_imp_l7');
            $table->dropColumn('pmt_clk_l60');
            $table->dropColumn('pmt_clk_l30');
            $table->dropColumn('pmt_clk_l7');
            $table->dropColumn('pmt_spnd_l30');
            $table->dropColumn('pmt_spnd_l7');
            $table->dropColumn('pmt_sld_l30');
            $table->dropColumn('pmt_sld_l7');
            $table->dropColumn('pmt_sls_l30');
            $table->dropColumn('pmt_sls_l7');
            $table->dropColumn('pmt_percent_rate');
            $table->dropColumn('oc_l30');
            $table->dropColumn('oc_l60');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebay_metrics', function (Blueprint $table) {
            //
        });
    }
};
