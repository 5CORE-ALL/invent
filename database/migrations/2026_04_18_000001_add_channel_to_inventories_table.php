<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventories')) {
            return;
        }
        if (Schema::hasColumn('inventories', 'channel')) {
            return;
        }
        Schema::table('inventories', function (Blueprint $table) {
            $table->string('channel', 255)->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventories') || ! Schema::hasColumn('inventories', 'channel')) {
            return;
        }
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
