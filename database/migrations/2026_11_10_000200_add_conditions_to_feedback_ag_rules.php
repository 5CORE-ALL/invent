<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feedback_ag_rules')) {
            return;
        }

        Schema::table('feedback_ag_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('feedback_ag_rules', 'conditions')) {
                // List of {field, operator, value}. The first one is mirrored in field/operator/value.
                $table->json('conditions')->nullable()->after('value');
            }
            if (! Schema::hasColumn('feedback_ag_rules', 'match_mode')) {
                // all = every condition must match (AND), any = at least one (OR)
                $table->string('match_mode', 3)->default('all')->after('conditions');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('feedback_ag_rules')) {
            return;
        }

        Schema::table('feedback_ag_rules', function (Blueprint $table) {
            foreach (['match_mode', 'conditions'] as $col) {
                if (Schema::hasColumn('feedback_ag_rules', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
