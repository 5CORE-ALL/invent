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
        if (! Schema::hasTable('ebay_priority_reports')) {
            return;
        }

        if (! Schema::hasColumn('ebay_priority_reports', 'last_sbid')) {
            Schema::table('ebay_priority_reports', function (Blueprint $table) {
                $table->string('last_sbid')->nullable()->after('cost_per_click');
            });
        }
        if (! Schema::hasColumn('ebay_priority_reports', 'sbid_m')) {
            Schema::table('ebay_priority_reports', function (Blueprint $table) {
                $table->string('sbid_m')->nullable()->after('last_sbid');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ebay_priority_reports')) {
            return;
        }

        $columns = ['last_sbid', 'sbid_m'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('ebay_priority_reports', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('ebay_priority_reports', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
