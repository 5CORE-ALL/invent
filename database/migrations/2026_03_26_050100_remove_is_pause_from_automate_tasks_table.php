<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('automate_tasks')) {
            return;
        }

        if (Schema::hasColumn('automate_tasks', 'is_pause')) {
            // Force all automated task templates to active before removing pause support.
            DB::table('automate_tasks')->update(['is_pause' => 0]);

            Schema::table('automate_tasks', function (Blueprint $table) {
                $table->dropColumn('is_pause');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('automate_tasks')) {
            return;
        }

        if (!Schema::hasColumn('automate_tasks', 'is_pause')) {
            Schema::table('automate_tasks', function (Blueprint $table) {
                $table->unsignedTinyInteger('is_pause')->default(0);
            });
        }
    }
};
