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
            ['action_1', 'issue', fn (Blueprint $t) => $t->string('action_1')->nullable()],
            ['action_2', 'action_1', fn (Blueprint $t) => $t->string('action_2')->nullable()],
            ['c_action_1', 'action_2', fn (Blueprint $t) => $t->string('c_action_1')->nullable()],
            ['c_action_2', 'c_action_1', fn (Blueprint $t) => $t->string('c_action_2')->nullable()],
            ['close_note', 'c_action_2', fn (Blueprint $t) => $t->string('close_note')->nullable()],
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

        $names = ['close_note', 'c_action_2', 'c_action_1', 'action_2', 'action_1'];
        $toDrop = array_values(array_filter($names, fn (string $n) => Schema::hasColumn($tableName, $n)));

        if ($toDrop !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }
    }
};
