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
        // Idempotent: only add the column if the table exists and the column
        // isn't already present (the duplicate 061219 migration ships with it).
        if (!Schema::hasTable('audit_suggestion_history')) {
            return;
        }
        if (Schema::hasColumn('audit_suggestion_history', 'action_type')) {
            return;
        }

        Schema::table('audit_suggestion_history', function (Blueprint $table) {
            $table->string('action_type')->nullable()->after('audit_suggestion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('audit_suggestion_history')) {
            return;
        }
        if (!Schema::hasColumn('audit_suggestion_history', 'action_type')) {
            return;
        }

        Schema::table('audit_suggestion_history', function (Blueprint $table) {
            $table->dropColumn('action_type');
        });
    }
};
