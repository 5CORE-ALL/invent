<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tasks') && ! Schema::hasColumn('tasks', 'screenshots')) {
            Schema::table('tasks', function (Blueprint $table) {
                $column = $table->json('screenshots')->nullable();
                if (Schema::hasColumn('tasks', 'image')) {
                    $column->after('image');
                }
            });
        }

        if (Schema::hasTable('deleted_tasks') && ! Schema::hasColumn('deleted_tasks', 'screenshots')) {
            Schema::table('deleted_tasks', function (Blueprint $table) {
                $column = $table->json('screenshots')->nullable();
                if (Schema::hasColumn('deleted_tasks', 'image')) {
                    $column->after('image');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'screenshots')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('screenshots');
            });
        }

        if (Schema::hasTable('deleted_tasks') && Schema::hasColumn('deleted_tasks', 'screenshots')) {
            Schema::table('deleted_tasks', function (Blueprint $table) {
                $table->dropColumn('screenshots');
            });
        }
    }
};
