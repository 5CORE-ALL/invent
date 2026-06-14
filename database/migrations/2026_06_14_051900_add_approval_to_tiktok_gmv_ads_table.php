<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiktok_gmv_ads', function (Blueprint $table) {
            if (!Schema::hasColumn('tiktok_gmv_ads', 'approval')) {
                $table->string('approval', 32)->default('Pending')->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tiktok_gmv_ads', function (Blueprint $table) {
            if (Schema::hasColumn('tiktok_gmv_ads', 'approval')) {
                $table->dropColumn('approval');
            }
        });
    }
};
