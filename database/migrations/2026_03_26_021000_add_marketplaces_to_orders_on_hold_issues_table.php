<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_on_hold_issues', function (Blueprint $table) {
            $table->string('marketplace_1')->nullable()->after('parent');
            $table->string('marketplace_2')->nullable()->after('marketplace_1');
        });
    }

    public function down(): void
    {
        Schema::table('orders_on_hold_issues', function (Blueprint $table) {
            $table->dropColumn(['marketplace_1', 'marketplace_2']);
        });
    }
};
