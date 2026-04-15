<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventories')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            if (! Schema::hasColumn('inventories', 'shopify_adjustment_succeeded_at')) {
                $table->timestamp('shopify_adjustment_succeeded_at')->nullable()->after('shopify_retry_count');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventories')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'shopify_adjustment_succeeded_at')) {
                $table->dropColumn('shopify_adjustment_succeeded_at');
            }
        });
    }
};
