<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }
        if (! Schema::hasColumn('channel_master', 'ch_orders_link')) {
            Schema::table('channel_master', function (Blueprint $table) {
                $table->string('ch_orders_link', 1000)->nullable()->after('missing_link');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }
        if (Schema::hasColumn('channel_master', 'ch_orders_link')) {
            Schema::table('channel_master', function (Blueprint $table) {
                $table->dropColumn('ch_orders_link');
            });
        }
    }
};
