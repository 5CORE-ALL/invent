<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_ads_master', function (Blueprint $table) {
            $table->boolean('is_checked')->default(false)->after('link');
            $table->string('checked_by')->nullable()->after('is_checked');
            $table->timestamp('checked_at')->nullable()->after('checked_by');
        });
    }

    public function down(): void
    {
        Schema::table('video_ads_master', function (Blueprint $table) {
            $table->dropColumn(['is_checked', 'checked_by', 'checked_at']);
        });
    }
};
