<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements') || Schema::hasColumn('announcements', 'posted_at')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            $table->timestamp('posted_at')->nullable()->after('announced_on');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('announcements') || ! Schema::hasColumn('announcements', 'posted_at')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('posted_at');
        });
    }
};
