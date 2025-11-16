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
        // Run only if table exists
        if (Schema::hasTable('transit_container_details')) {
            Schema::table('transit_container_details', function (Blueprint $table) {
                if (!Schema::hasColumn('transit_container_details', 'auth_user')) {
                    $table->string('auth_user')->nullable()->after('comparison_link');
                }

                if (!Schema::hasColumn('transit_container_details', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('transit_container_details')) {
            Schema::table('transit_container_details', function (Blueprint $table) {
                if (Schema::hasColumn('transit_container_details', 'auth_user')) {
                    $table->dropColumn('auth_user');
                }

                if (Schema::hasColumn('transit_container_details', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
