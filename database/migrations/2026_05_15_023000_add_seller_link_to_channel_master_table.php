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
        if (! Schema::hasTable('channel_master')) {
            return;
        }
        if (! Schema::hasColumn('channel_master', 'seller_link')) {
            Schema::table('channel_master', function (Blueprint $table) {
                $table->string('seller_link', 1000)->nullable()->after('logo');
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
        if (Schema::hasColumn('channel_master', 'seller_link')) {
            Schema::table('channel_master', function (Blueprint $table) {
                $table->dropColumn('seller_link');
            });
        }
    }
};
