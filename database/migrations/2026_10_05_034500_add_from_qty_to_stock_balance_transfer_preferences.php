<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_balance_transfer_preferences')) {
            return;
        }
        if (Schema::hasColumn('stock_balance_transfer_preferences', 'from_qty')) {
            return;
        }

        Schema::table('stock_balance_transfer_preferences', function (Blueprint $table) {
            $table->unsignedInteger('from_qty')->nullable()->after('ratio');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_balance_transfer_preferences')) {
            return;
        }
        if (! Schema::hasColumn('stock_balance_transfer_preferences', 'from_qty')) {
            return;
        }

        Schema::table('stock_balance_transfer_preferences', function (Blueprint $table) {
            $table->dropColumn('from_qty');
        });
    }
};
