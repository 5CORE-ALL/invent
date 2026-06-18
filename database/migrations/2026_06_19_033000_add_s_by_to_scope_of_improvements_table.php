<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scope_of_improvements', function (Blueprint $table) {
            // The original create migration was edited after it ran on some
            // environments, so several columns are missing. Add anything not
            // already present, plus the new "s_by" column.
            if (!Schema::hasColumn('scope_of_improvements', 'issue')) {
                $table->text('issue')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('scope_of_improvements', 'root_cause')) {
                $table->text('root_cause')->nullable()->after('issue');
            }
            if (!Schema::hasColumn('scope_of_improvements', 'fixing_root_cause')) {
                $table->text('fixing_root_cause')->nullable()->after('root_cause');
            }
            if (!Schema::hasColumn('scope_of_improvements', 's_by')) {
                $table->string('s_by')->nullable()->after('fixing_root_cause');
            }
            if (!Schema::hasColumn('scope_of_improvements', 'history')) {
                $table->json('history')->nullable()->after('s_by');
            }
            if (!Schema::hasColumn('scope_of_improvements', 'created_by')) {
                $table->string('created_by')->nullable()->after('history');
            }
            if (!Schema::hasColumn('scope_of_improvements', 'updated_by')) {
                $table->string('updated_by')->nullable()->after('created_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scope_of_improvements', function (Blueprint $table) {
            if (Schema::hasColumn('scope_of_improvements', 's_by')) {
                $table->dropColumn('s_by');
            }
        });
    }
};
