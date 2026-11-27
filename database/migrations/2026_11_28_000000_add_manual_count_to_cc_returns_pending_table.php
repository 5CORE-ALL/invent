<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cc_returns_pending') || Schema::hasColumn('cc_returns_pending', 'manual_count')) {
            return;
        }

        Schema::table('cc_returns_pending', function (Blueprint $table) {
            // Hand-entered pending count for channels that have no returns API
            // (or whose API pull is failing). Never overwritten by API pulls.
            $table->unsignedInteger('manual_count')->nullable()->after('pending_count');
            $table->timestamp('manual_updated_at')->nullable()->after('manual_count');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cc_returns_pending') || ! Schema::hasColumn('cc_returns_pending', 'manual_count')) {
            return;
        }

        Schema::table('cc_returns_pending', function (Blueprint $table) {
            $table->dropColumn(['manual_count', 'manual_updated_at']);
        });
    }
};
