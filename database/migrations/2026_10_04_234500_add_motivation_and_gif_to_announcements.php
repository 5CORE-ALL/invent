<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'motivation')) {
                $table->string('motivation', 280)->nullable()->after('message');
            }
            if (! Schema::hasColumn('announcements', 'gif_url')) {
                $table->string('gif_url', 500)->nullable()->after('images');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            if (Schema::hasColumn('announcements', 'gif_url')) {
                $table->dropColumn('gif_url');
            }
            if (Schema::hasColumn('announcements', 'motivation')) {
                $table->dropColumn('motivation');
            }
        });
    }
};
