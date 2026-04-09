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
        if (! Schema::hasTable('tasks')) {
            return;
        }

        if (! Schema::hasColumn('tasks', 'atc')) {
            $afterEtc = Schema::hasColumn('tasks', 'etc_minutes');
            Schema::table('tasks', function (Blueprint $table) use ($afterEtc) {
                $column = $table->integer('atc')->nullable()->comment('Actual Time to Complete in minutes');
                if ($afterEtc) {
                    $column->after('etc_minutes');
                }
            });
        }
        if (! Schema::hasColumn('tasks', 'rework_reason')) {
            $afterAtc = Schema::hasColumn('tasks', 'atc');
            Schema::table('tasks', function (Blueprint $table) use ($afterAtc) {
                $column = $table->text('rework_reason')->nullable()->comment('Reason for rework');
                if ($afterAtc) {
                    $column->after('atc');
                }
            });
        }
        if (! Schema::hasColumn('tasks', 'completed_at')) {
            $afterRework = Schema::hasColumn('tasks', 'rework_reason');
            Schema::table('tasks', function (Blueprint $table) use ($afterRework) {
                $column = $table->dateTime('completed_at')->nullable()->comment('Task completion timestamp');
                if ($afterRework) {
                    $column->after('rework_reason');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        $columns = ['atc', 'rework_reason', 'completed_at'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('tasks', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
