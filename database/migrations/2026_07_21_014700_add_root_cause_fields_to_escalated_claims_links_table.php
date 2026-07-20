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
            if (! Schema::hasColumn('escalated_claims_links', 'root_cause_found')) {
                $table->text('root_cause_found')->nullable()->after('current_parameter');
            }
            if (! Schema::hasColumn('escalated_claims_links', 'action_to_fix')) {
                $table->text('action_to_fix')->nullable()->after('root_cause_found');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('escalated_claims_links')) {
            return;
        }

        Schema::table('escalated_claims_links', function (Blueprint $table) {
            if (Schema::hasColumn('escalated_claims_links', 'action_to_fix')) {
                $table->dropColumn('action_to_fix');
            }
            if (Schema::hasColumn('escalated_claims_links', 'root_cause_found')) {
                $table->dropColumn('root_cause_found');
            }
        });
    }
};
