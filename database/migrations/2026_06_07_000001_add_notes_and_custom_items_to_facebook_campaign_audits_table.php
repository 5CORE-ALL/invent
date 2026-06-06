<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds per-check Notes + a free-form "manual" checklist to the Meta
 * (Facebook) campaign audit log.
 *
 *  - `notes`         JSON map { check_key: note_string } — one editable
 *                    note per checklist row (built-in or custom).
 *  - `custom_items`  JSON array of manually-added checks:
 *                    [{ key, label, weight, checked }] — these are
 *                    captured at audit time so history stays readable
 *                    even after the built-in checklist changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_campaign_audits', function (Blueprint $table) {
            $table->json('notes')->nullable()->after('checks');
            $table->json('custom_items')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('facebook_campaign_audits', function (Blueprint $table) {
            $table->dropColumn(['notes', 'custom_items']);
        });
    }
};
