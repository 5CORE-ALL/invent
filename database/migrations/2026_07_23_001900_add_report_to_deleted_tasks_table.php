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
        if (! Schema::hasColumn('deleted_tasks', 'report')) {
            Schema::table('deleted_tasks', function (Blueprint $table) {
                $table->text('report')->nullable()->after('rework_reason');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('deleted_tasks', 'report')) {
            Schema::table('deleted_tasks', function (Blueprint $table) {
                $table->dropColumn('report');
            });
        }
    }
};
