<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_balance_rules')) {
            return;
        }
        if (Schema::hasColumn('stock_balance_rules', 'from_items')) {
            return;
        }

        Schema::table('stock_balance_rules', function (Blueprint $table) {
            $table->json('from_items')->nullable()->after('from_qty');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_balance_rules') || ! Schema::hasColumn('stock_balance_rules', 'from_items')) {
            return;
        }

        Schema::table('stock_balance_rules', function (Blueprint $table) {
            $table->dropColumn('from_items');
        });
    }
};
