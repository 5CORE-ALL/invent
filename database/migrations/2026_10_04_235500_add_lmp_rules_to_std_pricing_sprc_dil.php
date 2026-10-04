<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('std_pricing_sprc_dil') || Schema::hasColumn('std_pricing_sprc_dil', 'lmp_rules')) {
            return;
        }

        Schema::table('std_pricing_sprc_dil', function (Blueprint $table) {
            $table->json('lmp_rules')->nullable()->after('clearance_nroi');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('std_pricing_sprc_dil') || ! Schema::hasColumn('std_pricing_sprc_dil', 'lmp_rules')) {
            return;
        }

        Schema::table('std_pricing_sprc_dil', function (Blueprint $table) {
            $table->dropColumn('lmp_rules');
        });
    }
};
