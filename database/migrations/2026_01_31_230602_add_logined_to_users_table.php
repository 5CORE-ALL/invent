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
        if (Schema::hasColumn('users', 'logined')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('logined')->default(0)->after('password')->comment('0=logged out, 1=logged in');
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
        if (! Schema::hasColumn('users', 'logined')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('logined');
        });
    }
};
