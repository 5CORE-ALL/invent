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
        Schema::table('facebook_video_ads', function (Blueprint $table) {
            if (!Schema::hasColumn('facebook_video_ads', 'group_id')) {
                $table->unsignedBigInteger('group_id')->nullable()->after('sku');
                $table->index('group_id');
            }
            if (!Schema::hasColumn('facebook_video_ads', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('group_id');
                $table->index('category_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facebook_video_ads', function (Blueprint $table) {
            if (Schema::hasColumn('facebook_video_ads', 'group_id')) {
                try {
                    $table->dropIndex(['group_id']);
                } catch (\Exception $e) {
                    // Index might not exist
                }
                $table->dropColumn('group_id');
            }
            if (Schema::hasColumn('facebook_video_ads', 'category_id')) {
                try {
                    $table->dropIndex(['category_id']);
                } catch (\Exception $e) {
                    // Index might not exist
                }
                $table->dropColumn('category_id');
            }
        });
    }
};
