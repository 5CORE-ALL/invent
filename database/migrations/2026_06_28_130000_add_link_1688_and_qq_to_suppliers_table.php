<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('suppliers')) {
            return;
        }

        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'link_1688')) {
                $table->string('link_1688')->nullable()->after('alibaba');
            }
            if (!Schema::hasColumn('suppliers', 'qq')) {
                $table->string('qq')->nullable()->after('link_1688');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('suppliers')) {
            return;
        }

        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'qq')) {
                $table->dropColumn('qq');
            }
            if (Schema::hasColumn('suppliers', 'link_1688')) {
                $table->dropColumn('link_1688');
            }
        });
    }
};
