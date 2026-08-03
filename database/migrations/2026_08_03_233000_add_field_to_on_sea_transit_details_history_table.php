<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('on_sea_transit_details_history', function (Blueprint $table) {
            if (!Schema::hasColumn('on_sea_transit_details_history', 'field')) {
                $table->string('field', 64)->nullable()->after('container_sl_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('on_sea_transit_details_history', function (Blueprint $table) {
            if (Schema::hasColumn('on_sea_transit_details_history', 'field')) {
                $table->dropColumn('field');
            }
        });
    }
};
