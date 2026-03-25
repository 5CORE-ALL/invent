<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_on_hold_issue_histories', function (Blueprint $table) {
            $table->unsignedInteger('revision_no')->nullable()->after('event_type');
        });
    }

    public function down(): void
    {
        Schema::table('orders_on_hold_issue_histories', function (Blueprint $table) {
            $table->dropColumn('revision_no');
        });
    }
};
