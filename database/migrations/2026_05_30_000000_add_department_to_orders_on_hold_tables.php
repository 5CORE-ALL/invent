<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @return list<string> */
    private function tables(): array
    {
        return [
            'orders_on_hold_issues',
            'orders_on_hold_issue_histories',
        ];
    }

    public function up(): void
    {
        foreach ($this->tables() as $tbl) {
            if (! Schema::hasTable($tbl) || Schema::hasColumn($tbl, 'department')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->text('department')->nullable()->after('c_action_1_remark');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $tbl) {
            if (! Schema::hasTable($tbl) || ! Schema::hasColumn($tbl, 'department')) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) {
                $table->dropColumn('department');
            });
        }
    }
};
