<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_ads_master', function (Blueprint $table) {
            // Multi-tag HOOK values are pipe-delimited and can exceed 255 chars.
            try {
                $table->dropIndex(['hook_name']);
            } catch (\Throwable $e) {
                // Index name may differ across environments; ignore if absent.
            }
            $table->text('hook_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('video_ads_master', function (Blueprint $table) {
            $table->string('hook_name')->nullable()->change();
            $table->index('hook_name');
        });
    }
};
