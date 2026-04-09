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
        if (! Schema::hasTable('temu_metrics') || Schema::hasColumn('temu_metrics', 'temu_sheet_price')) {
            return;
        }

        $afterBasePrice = Schema::hasColumn('temu_metrics', 'base_price');

        Schema::table('temu_metrics', function (Blueprint $table) use ($afterBasePrice): void {
            if ($afterBasePrice) {
                $table->decimal('temu_sheet_price', 10, 2)->nullable()->after('base_price');
            } else {
                $table->decimal('temu_sheet_price', 10, 2)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('temu_metrics') || ! Schema::hasColumn('temu_metrics', 'temu_sheet_price')) {
            return;
        }

        Schema::table('temu_metrics', function (Blueprint $table) {
            $table->dropColumn('temu_sheet_price');
        });
    }
};
