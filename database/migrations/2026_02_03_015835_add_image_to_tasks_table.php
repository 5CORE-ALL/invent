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
        if (Schema::hasColumn('tasks', 'image')) {
            return;
        }

        $afterLink9 = Schema::hasColumn('tasks', 'link9');
        Schema::table('tasks', function (Blueprint $table) use ($afterLink9) {
            $column = $table->string('image')->nullable();
            if ($afterLink9) {
                $column->after('link9');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }
        if (! Schema::hasColumn('tasks', 'image')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
