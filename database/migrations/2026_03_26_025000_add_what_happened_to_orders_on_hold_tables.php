<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_on_hold_issues', function (Blueprint $table) {
            $table->string('what_happened', 50)->nullable()->after('marketplace_2');
        });

        Schema::table('orders_on_hold_issue_histories', function (Blueprint $table) {
            $table->string('what_happened', 50)->nullable()->after('marketplace_2');
        });
    }

    public function down(): void
    {
        Schema::table('orders_on_hold_issue_histories', function (Blueprint $table) {
            $table->dropColumn('what_happened');
        });

        Schema::table('orders_on_hold_issues', function (Blueprint $table) {
            $table->dropColumn('what_happened');
        });
    }
};
