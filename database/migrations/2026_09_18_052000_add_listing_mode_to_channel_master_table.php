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
        if (! Schema::hasColumn('channel_master', 'listing_mode')) {
            Schema::table('channel_master', function (Blueprint $table) {
                $after = Schema::hasColumn('channel_master', 'seller_link') ? 'seller_link' : 'logo';
                $table->string('listing_mode', 16)->nullable()->after($after);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }
        if (Schema::hasColumn('channel_master', 'listing_mode')) {
            Schema::table('channel_master', function (Blueprint $table) {
                $table->dropColumn('listing_mode');
            });
        }
    }
};
