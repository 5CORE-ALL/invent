<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional dollar amounts for incoming return rows (net = loss_usd − restock_fee_usd).
     */
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (! Schema::hasColumn('inventories', 'loss_usd')) {
                $table->decimal('loss_usd', 12, 2)->nullable()->after('loss_gain');
            }
            if (! Schema::hasColumn('inventories', 'restock_fee_usd')) {
                $table->decimal('restock_fee_usd', 12, 2)->nullable()->after('loss_usd');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'restock_fee_usd')) {
                $table->dropColumn('restock_fee_usd');
            }
            if (Schema::hasColumn('inventories', 'loss_usd')) {
                $table->dropColumn('loss_usd');
            }
        });
    }
};
