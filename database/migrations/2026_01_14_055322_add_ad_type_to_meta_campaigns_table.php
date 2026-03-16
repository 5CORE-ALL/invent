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
        Schema::table('meta_campaigns', function (Blueprint $table) {
            $table->string('ad_type')->nullable()->after('meta_id');
            $table->index('ad_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meta_campaigns', function (Blueprint $table) {
            $table->dropIndex(['ad_type']);
            $table->dropColumn('ad_type');
        });
    }
};
