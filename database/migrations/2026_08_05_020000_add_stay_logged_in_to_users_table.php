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
        if (! Schema::hasTable('users')) {
            return;
        }
        if (Schema::hasColumn('users', 'stay_logged_in')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('stay_logged_in')->default(0)->after('logined')
                ->comment('0=normal logout schedule, 1=exempt from auto-logout');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        if (! Schema::hasColumn('users', 'stay_logged_in')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('stay_logged_in');
        });
    }
};
