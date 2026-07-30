<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('channel_tabulator_column_settings')) {
            return;
        }
        if (Schema::hasColumn('channel_tabulator_column_settings', 'column_order')) {
            return;
        }

        Schema::table('channel_tabulator_column_settings', function (Blueprint $table) {
            $table->json('column_order')->nullable()->after('visibility');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('channel_tabulator_column_settings')) {
            return;
        }
        if (! Schema::hasColumn('channel_tabulator_column_settings', 'column_order')) {
            return;
        }

        Schema::table('channel_tabulator_column_settings', function (Blueprint $table) {
            $table->dropColumn('column_order');
        });
    }
};
