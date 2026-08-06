<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_orders') || Schema::hasColumn('purchase_orders', 'approvals')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->json('approvals')->nullable()->after('items');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_orders') || ! Schema::hasColumn('purchase_orders', 'approvals')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('approvals');
        });
    }
};
