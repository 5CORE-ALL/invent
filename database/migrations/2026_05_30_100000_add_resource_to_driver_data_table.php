<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_data') && ! Schema::hasColumn('driver_data', 'resource')) {
            Schema::table('driver_data', function (Blueprint $table) {
                $table->string('resource', 64)->nullable()->after('tag')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('driver_data') && Schema::hasColumn('driver_data', 'resource')) {
            Schema::table('driver_data', function (Blueprint $table) {
                $table->dropColumn('resource');
            });
        }
    }
};
