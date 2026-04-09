<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'orders_on_hold_issue_histories';

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'revision_no')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'event_type')) {
                $table->unsignedInteger('revision_no')->nullable()->after('event_type');
            } else {
                $table->unsignedInteger('revision_no')->nullable();
            }
        });
    }

    public function down(): void
    {
        $tableName = 'orders_on_hold_issue_histories';

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'revision_no')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('revision_no');
        });
    }
};
