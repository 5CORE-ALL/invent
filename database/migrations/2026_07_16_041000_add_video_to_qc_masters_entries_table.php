<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qc_masters_entries')) {
            return;
        }

        Schema::table('qc_masters_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('qc_masters_entries', 'video_path')) {
                $table->string('video_path', 500)->nullable()->after('image_size_kb');
            }
            if (! Schema::hasColumn('qc_masters_entries', 'video_size_kb')) {
                $table->unsignedInteger('video_size_kb')->nullable()->after('video_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('qc_masters_entries')) {
            return;
        }

        Schema::table('qc_masters_entries', function (Blueprint $table) {
            if (Schema::hasColumn('qc_masters_entries', 'video_size_kb')) {
                $table->dropColumn('video_size_kb');
            }
            if (Schema::hasColumn('qc_masters_entries', 'video_path')) {
                $table->dropColumn('video_path');
            }
        });
    }
};
