<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lqs_master_entries') || Schema::hasColumn('lqs_master_entries', 'notes')) {
            return;
        }

        Schema::table('lqs_master_entries', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('lqs');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lqs_master_entries') || ! Schema::hasColumn('lqs_master_entries', 'notes')) {
            return;
        }

        Schema::table('lqs_master_entries', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
