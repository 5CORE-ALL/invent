<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_on_hold_issues', function (Blueprint $table) {
            $table->string('action_1')->nullable()->after('issue');
            $table->string('action_2')->nullable()->after('action_1');
            $table->string('c_action_1')->nullable()->after('action_2');
            $table->string('c_action_2')->nullable()->after('c_action_1');
            $table->string('close_note')->nullable()->after('c_action_2');
        });
    }

    public function down(): void
    {
        Schema::table('orders_on_hold_issues', function (Blueprint $table) {
            $table->dropColumn(['action_1', 'action_2', 'c_action_1', 'c_action_2', 'close_note']);
        });
    }
};
