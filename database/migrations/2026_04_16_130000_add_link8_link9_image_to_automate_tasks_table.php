<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('automate_tasks')) {
            return;
        }

        Schema::table('automate_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('automate_tasks', 'link8')) {
                $table->text('link8')->nullable();
            }
            if (!Schema::hasColumn('automate_tasks', 'link9')) {
                $table->text('link9')->nullable();
            }
            if (!Schema::hasColumn('automate_tasks', 'image')) {
                $table->string('image', 512)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('automate_tasks')) {
            return;
        }

        Schema::table('automate_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('automate_tasks', 'image')) {
                $table->dropColumn('image');
            }
            if (Schema::hasColumn('automate_tasks', 'link9')) {
                $table->dropColumn('link9');
            }
            if (Schema::hasColumn('automate_tasks', 'link8')) {
                $table->dropColumn('link8');
            }
        });
    }
};
