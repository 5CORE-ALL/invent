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
        Schema::table('temu_metrics', function (Blueprint $table) {
            $table->decimal('temu_sheet_price', 10, 2)->nullable()->after('base_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temu_metrics', function (Blueprint $table) {
            $table->dropColumn('temu_sheet_price');
        });
    }
};
