<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('escalated_claims_links')) {
            return;
        }

        Schema::table('escalated_claims_links', function (Blueprint $table) {
            if (! Schema::hasColumn('escalated_claims_links', 'summary_issues')) {
                $table->text('summary_issues')->nullable()->after('current_parameter');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('escalated_claims_links')) {
            return;
        }

        Schema::table('escalated_claims_links', function (Blueprint $table) {
            if (Schema::hasColumn('escalated_claims_links', 'summary_issues')) {
                $table->dropColumn('summary_issues');
            }
        });
    }
};
