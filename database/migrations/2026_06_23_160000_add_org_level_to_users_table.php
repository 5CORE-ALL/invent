<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an organisational level / role column on users.
 *
 * Distinct from `role` (system role: admin / user) and `designation`
 * (job title). `org_level` captures Mgr / Director / Exec for the Task
 * Summary "Role" column. When set to "mgr", the row exposes a quick-tag
 * modal to assign the juniors the manager is responsible for; the same
 * pivot (manager_juniors) is shared with CL Mgr so data stays in sync.
 *
 * Allowed values: 'mgr', 'director', 'exec', NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        if (Schema::hasColumn('users', 'org_level')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('org_level', 20)->nullable()->after('designation');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        if (! Schema::hasColumn('users', 'org_level')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('org_level');
        });
    }
};
