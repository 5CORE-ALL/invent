<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual Promotions percentage entered per channel on /all-marketplace-master.
     */
    public function up(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }
        if (! Schema::hasColumn('channel_master', 'promotions')) {
            Schema::table('channel_master', function (Blueprint $table) {
                $table->decimal('promotions', 8, 2)->nullable()->after('channel_percentage');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }
        if (Schema::hasColumn('channel_master', 'promotions')) {
            Schema::table('channel_master', function (Blueprint $table) {
                $table->dropColumn('promotions');
            });
        }
    }
};
