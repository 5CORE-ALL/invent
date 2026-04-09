<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopify_orders')) {
            return;
        }

        if (! Schema::hasColumn('shopify_orders', 'last_synced_at')) {
            Schema::table('shopify_orders', function (Blueprint $table) {
                $table->timestamp('last_synced_at')->nullable()->index()->after('order_date');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('shopify_orders')) {
            return;
        }

        if (Schema::hasColumn('shopify_orders', 'last_synced_at')) {
            Schema::table('shopify_orders', function (Blueprint $table) {
                $table->dropColumn('last_synced_at');
            });
        }
    }
};
