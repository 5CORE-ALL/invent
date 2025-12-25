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
            }
            if (!Schema::hasColumn('facebook_video_ads', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('group_id');
            }
        });

        // Add indexes (foreign keys will be handled at application level for now)
        if (Schema::hasColumn('facebook_video_ads', 'group_id')) {
            try {
                Schema::table('facebook_video_ads', function (Blueprint $table) {
                    $table->index('group_id');
                });
            } catch (\Exception $e) {
                // Index might already exist
            }
        }
        if (Schema::hasColumn('facebook_video_ads', 'category_id')) {
            try {
                Schema::table('facebook_video_ads', function (Blueprint $table) {
                    $table->index('category_id');
                });
            } catch (\Exception $e) {
                // Index might already exist
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facebook_video_ads', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropForeign(['category_id']);
            $table->dropIndex(['group_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn(['group_id', 'category_id']);
        });
    }
};
