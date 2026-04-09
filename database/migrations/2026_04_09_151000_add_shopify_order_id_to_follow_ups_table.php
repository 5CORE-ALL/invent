<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('follow_ups')) {
            return;
        }

        if (! Schema::hasColumn('follow_ups', 'shopify_order_id')) {
            Schema::table('follow_ups', function (Blueprint $table) {
                $table->unsignedBigInteger('shopify_order_id')->nullable()->after('customer_id');
                $table->unique('shopify_order_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('follow_ups')) {
            return;
        }

        if (Schema::hasColumn('follow_ups', 'shopify_order_id')) {
            Schema::table('follow_ups', function (Blueprint $table) {
                $table->dropUnique(['shopify_order_id']);
                $table->dropColumn('shopify_order_id');
            });
        }
    }
};
