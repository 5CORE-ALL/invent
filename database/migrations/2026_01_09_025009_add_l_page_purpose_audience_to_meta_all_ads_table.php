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
        Schema::table('meta_all_ads', function (Blueprint $table) {
            $table->string('l_page', 255)->nullable()->after('group_id');
            $table->string('purpose', 255)->nullable()->after('l_page');
            $table->string('audience', 255)->nullable()->after('purpose');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meta_all_ads', function (Blueprint $table) {
            $table->dropColumn(['l_page', 'purpose', 'audience']);
        });
    }
};
