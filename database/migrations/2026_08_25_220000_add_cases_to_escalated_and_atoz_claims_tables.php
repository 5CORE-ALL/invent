<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('escalated_claims_links') && ! Schema::hasColumn('escalated_claims_links', 'cases')) {
            Schema::table('escalated_claims_links', function (Blueprint $table) {
                $table->json('cases')->nullable()->after('action_to_fix');
            });
        }

        if (Schema::hasTable('atoz_claims_rate') && ! Schema::hasColumn('atoz_claims_rate', 'cases')) {
            Schema::table('atoz_claims_rate', function (Blueprint $table) {
                $table->json('cases')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('escalated_claims_links') && Schema::hasColumn('escalated_claims_links', 'cases')) {
            Schema::table('escalated_claims_links', function (Blueprint $table) {
                $table->dropColumn('cases');
            });
        }

        if (Schema::hasTable('atoz_claims_rate') && Schema::hasColumn('atoz_claims_rate', 'cases')) {
            Schema::table('atoz_claims_rate', function (Blueprint $table) {
                $table->dropColumn('cases');
            });
        }
    }
};
