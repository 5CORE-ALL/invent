<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the optional attachment image (≤ 1 MB) uploaded with each
 * customer follow-up. Path is the storage key on the `public` disk
 * (e.g. customer-care/followups/abc123.jpg) — same convention used
 * by DispatchIssuesController for issue images.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_followups')) {
            return;
        }
        if (Schema::hasColumn('customer_followups', 'image_path')) {
            return;
        }

        Schema::table('customer_followups', function (Blueprint $table) {
            $table->string('image_path', 512)->nullable()->after('reference_link');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_followups')) {
            return;
        }
        if (!Schema::hasColumn('customer_followups', 'image_path')) {
            return;
        }

        Schema::table('customer_followups', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
