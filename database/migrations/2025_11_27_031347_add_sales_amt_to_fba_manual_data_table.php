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
        Schema::table('fba_manual_data', function (Blueprint $table) {
            $table->decimal('sales_amt', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fba_manual_data', function (Blueprint $table) {
            $table->dropColumn('sales_amt');
        });
    }
};
