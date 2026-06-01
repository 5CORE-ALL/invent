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
            if (! Schema::hasColumn('inventories', 'shopify_adjustment_status')) {
                $table->string('shopify_adjustment_status', 32)->nullable()->after('to_adjust');
            }
            if (! Schema::hasColumn('inventories', 'shopify_adjustment_error')) {
                $table->text('shopify_adjustment_error')->nullable()->after('shopify_adjustment_status');
            }
            if (! Schema::hasColumn('inventories', 'shopify_retry_count')) {
                $table->unsignedTinyInteger('shopify_retry_count')->default(0)->after('shopify_adjustment_error');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventories')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'shopify_retry_count')) {
                $table->dropColumn('shopify_retry_count');
            }
            if (Schema::hasColumn('inventories', 'shopify_adjustment_error')) {
                $table->dropColumn('shopify_adjustment_error');
            }
            if (Schema::hasColumn('inventories', 'shopify_adjustment_status')) {
                $table->dropColumn('shopify_adjustment_status');
            }
        });
    }
};
