<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'report')) {
                $table->text('report')->nullable();
            }
            if (!Schema::hasColumn('tasks', 'reference_link')) {
                $table->string('reference_link', 2048)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'reference_link')) {
                $table->dropColumn('reference_link');
            }
            if (Schema::hasColumn('tasks', 'report')) {
                $table->dropColumn('report');
            }
        });
    }
};
