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

        $specs = [
            ['is_archived', 'created_by_user_id', fn (Blueprint $t) => $t->boolean('is_archived')->default(false)],
            ['archived_at', 'is_archived', fn (Blueprint $t) => $t->timestamp('archived_at')->nullable()],
            ['archived_by', 'archived_at', fn (Blueprint $t) => $t->string('archived_by')->nullable()],
        ];

        foreach ($specs as [$column, $after, $add]) {
            if (Schema::hasColumn($tableName, $column)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $after, $add) {
                $col = $add($table);
                if ($after !== null && Schema::hasColumn($tableName, $after)) {
                    $col->after($after);
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

        $names = ['archived_by', 'archived_at', 'is_archived'];
        $toDrop = array_values(array_filter($names, fn (string $n) => Schema::hasColumn($tableName, $n)));

        if ($toDrop !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }
    }
};
