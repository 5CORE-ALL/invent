<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual Compliance Count entered per channel on /all-marketplace-master.
     */
    public function up(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }
        if (! Schema::hasColumn('channel_master', 'compliance_count')) {
            Schema::table('channel_master', function (Blueprint $table) {
                $table->integer('compliance_count')->nullable()->after('promotions');
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
        if (Schema::hasColumn('channel_master', 'compliance_count')) {
            Schema::table('channel_master', function (Blueprint $table) {
                $table->dropColumn('compliance_count');
            });
        }
    }
};
