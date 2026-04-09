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
        if (! Schema::hasTable('ready_to_ship')) {
            return;
        }

        if (! Schema::hasColumn('ready_to_ship', 'auth_user')) {
            Schema::table('ready_to_ship', function (Blueprint $table) {
                $table->string('auth_user')->nullable()->after('transit_inv_status');
            });
        }
        if (! Schema::hasColumn('ready_to_ship', 'deleted_at')) {
            Schema::table('ready_to_ship', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ready_to_ship')) {
            return;
        }

        if (Schema::hasColumn('ready_to_ship', 'auth_user')) {
            Schema::table('ready_to_ship', function (Blueprint $table) {
                $table->dropColumn('auth_user');
            });
        }
        if (Schema::hasColumn('ready_to_ship', 'deleted_at')) {
            Schema::table('ready_to_ship', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
