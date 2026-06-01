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
        // This file is a duplicate of 2026_05_06_042002_create_audit_suggestion_history_table.
        // It is kept for migration-history compatibility on environments where it was
        // already recorded as run. Make the create idempotent so a fresh deploy that
        // runs the earlier file first won't crash here, and a fresh deploy that only
        // runs this one still ends up with the correct schema.
        if (!Schema::hasTable('audit_suggestion_history')) {
            Schema::create('audit_suggestion_history', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->index();
                $table->text('audit_suggestion')->nullable();
                $table->string('action_type')->nullable(); // 'FIXED' or null
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name')->nullable();
                $table->timestamps();

                $table->index(['sku', 'created_at']);
            });
            return;
        }

        // Table already exists — guarantee the action_type column is present in case
        // this is an older DB where only 042002 ran without the action_type migration.
        if (!Schema::hasColumn('audit_suggestion_history', 'action_type')) {
            Schema::table('audit_suggestion_history', function (Blueprint $table) {
                $table->string('action_type')->nullable()->after('audit_suggestion');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally a no-op: dropping here would also remove data that the
        // canonical 042002 migration owns. Use that migration's down() to drop.
    }
};
