<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL cannot keep a full-column index on TEXT without a prefix length.
        // Drop every non-primary index that includes hook_name before changing the type.
        $this->dropHookNameIndexes();

        Schema::table('video_ads_master', function (Blueprint $table) {
            // Multi-tag HOOK values are pipe-delimited and can exceed 255 chars.
            $table->text('hook_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('video_ads_master', function (Blueprint $table) {
            $table->string('hook_name')->nullable()->change();
        });

        // Restore a normal string index after reverting to VARCHAR.
        try {
            Schema::table('video_ads_master', function (Blueprint $table) {
                $table->index('hook_name');
            });
        } catch (\Throwable $e) {
            // Index may already exist.
        }
    }

    private function dropHookNameIndexes(): void
    {
        $indexes = DB::select("SHOW INDEX FROM `video_ads_master` WHERE Column_name = 'hook_name'");
        $dropped = [];

        foreach ($indexes as $index) {
            $keyName = $index->Key_name ?? null;
            if (!$keyName || $keyName === 'PRIMARY' || isset($dropped[$keyName])) {
                continue;
            }

            try {
                DB::statement("ALTER TABLE `video_ads_master` DROP INDEX `{$keyName}`");
                $dropped[$keyName] = true;
            } catch (\Throwable $e) {
                // Index may already be gone on some environments.
            }
        }
    }
};
