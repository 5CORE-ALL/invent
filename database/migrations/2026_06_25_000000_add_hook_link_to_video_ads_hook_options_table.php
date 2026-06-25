<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_ads_hook_options', function (Blueprint $table) {
            $table->text('hook')->nullable()->after('name');
            $table->text('link')->nullable()->after('hook');
        });
    }

    public function down(): void
    {
        Schema::table('video_ads_hook_options', function (Blueprint $table) {
            $table->dropColumn(['hook', 'link']);
        });
    }
};
