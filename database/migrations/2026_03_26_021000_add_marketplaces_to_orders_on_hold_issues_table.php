<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'orders_on_hold_issues';

        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'marketplace_1')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'parent')) {
                    $table->string('marketplace_1')->nullable()->after('parent');
                } else {
                    $table->string('marketplace_1')->nullable();
                }
            });
        }

        if (! Schema::hasColumn($tableName, 'marketplace_2')) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'marketplace_1')) {
                    $table->string('marketplace_2')->nullable()->after('marketplace_1');
                } else {
                    $table->string('marketplace_2')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $tableName = 'orders_on_hold_issues';

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn($tableName, 'marketplace_1') ? 'marketplace_1' : null,
            Schema::hasColumn($tableName, 'marketplace_2') ? 'marketplace_2' : null,
        ]));

        if ($columns !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
