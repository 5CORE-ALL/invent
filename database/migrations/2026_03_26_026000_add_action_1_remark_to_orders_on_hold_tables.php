<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_on_hold_issues', function (Blueprint $table) {
            $table->string('action_1_remark')->nullable()->after('action_1');
        });

        Schema::table('orders_on_hold_issue_histories', function (Blueprint $table) {
            $table->string('action_1_remark')->nullable()->after('action_1');
        });
    }

    public function down(): void
    {
        Schema::table('orders_on_hold_issue_histories', function (Blueprint $table) {
            $table->dropColumn('action_1_remark');
        });

        Schema::table('orders_on_hold_issues', function (Blueprint $table) {
            $table->dropColumn('action_1_remark');
        });
    }
};
