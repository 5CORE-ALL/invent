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
        Schema::table('fba_table', function (Blueprint $table) {
            $table->enum('nrl_fba', ['All', 'FBA', 'Both', 'NRL'])->default('All')->after('fulfillment_channel_sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fba_table', function (Blueprint $table) {
            $table->dropColumn('nrl_fba');
        });
    }
};
