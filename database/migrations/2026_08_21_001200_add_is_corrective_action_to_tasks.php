<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tasks') && ! Schema::hasColumn('tasks', 'is_corrective_action')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->boolean('is_corrective_action')->default(false)->after('group');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'is_corrective_action')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('is_corrective_action');
            });
        }
    }
};
