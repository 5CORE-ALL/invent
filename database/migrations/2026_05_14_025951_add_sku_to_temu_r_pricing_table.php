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
        Schema::table('temu_r_pricing', function (Blueprint $table) {
            // Add SKU column after sku_id
            if (!Schema::hasColumn('temu_r_pricing', 'sku')) {
                $table->string('sku')->nullable()->after('sku_id')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temu_r_pricing', function (Blueprint $table) {
            if (Schema::hasColumn('temu_r_pricing', 'sku')) {
                $table->dropColumn('sku');
            }
        });
    }
};
