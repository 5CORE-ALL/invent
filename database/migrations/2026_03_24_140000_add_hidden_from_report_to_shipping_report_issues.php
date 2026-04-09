<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'shipping_report_issues';

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'hidden_from_report')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'reason')) {
                $table->boolean('hidden_from_report')->default(false)->after('reason');
            } else {
                $table->boolean('hidden_from_report')->default(false);
            }
        });
    }

    public function down(): void
    {
        $tableName = 'shipping_report_issues';

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'hidden_from_report')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('hidden_from_report');
        });
    }
};
