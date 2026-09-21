<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual Brand Authorisation (Yes/No) entered per channel on /all-marketplace-master.
     */
    public function up(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }
        if (! Schema::hasColumn('channel_master', 'brand_authorisation')) {
            Schema::table('channel_master', function (Blueprint $table) {
                $table->string('brand_authorisation', 3)->nullable()->after('compliance_count');
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
        if (Schema::hasColumn('channel_master', 'brand_authorisation')) {
            Schema::table('channel_master', function (Blueprint $table) {
                $table->dropColumn('brand_authorisation');
            });
        }
    }
};
