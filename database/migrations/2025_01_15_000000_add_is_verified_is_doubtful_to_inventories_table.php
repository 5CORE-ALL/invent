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
        Schema::table('inventories', function (Blueprint $table) {
            if (!Schema::hasColumn('inventories', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('is_approved');
            }
            if (!Schema::hasColumn('inventories', 'is_doubtful')) {
                $table->boolean('is_doubtful')->default(false)->after('is_verified');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'is_verified')) {
                $table->dropColumn('is_verified');
            }
            if (Schema::hasColumn('inventories', 'is_doubtful')) {
                $table->dropColumn('is_doubtful');
            }
        });
    }
};
