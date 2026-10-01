<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_fulfillment_trackings')) {
            return;
        }

        Schema::table('order_fulfillment_trackings', function (Blueprint $table) {
            if (! Schema::hasColumn('order_fulfillment_trackings', 'shopify_order_id')) {
                $table->string('shopify_order_id', 64)->nullable()->after('checked_at');
            }
            if (! Schema::hasColumn('order_fulfillment_trackings', 'shopify_fulfilled_at')) {
                $table->timestamp('shopify_fulfilled_at')->nullable()->after('shopify_order_id');
            }
            if (! Schema::hasColumn('order_fulfillment_trackings', 'shopify_push_attempts')) {
                $table->unsignedTinyInteger('shopify_push_attempts')->default(0)->after('shopify_fulfilled_at');
            }
            if (! Schema::hasColumn('order_fulfillment_trackings', 'shopify_push_checked_at')) {
                $table->timestamp('shopify_push_checked_at')->nullable()->after('shopify_push_attempts');
            }
            if (! Schema::hasColumn('order_fulfillment_trackings', 'shopify_push_message')) {
                $table->string('shopify_push_message', 255)->nullable()->after('shopify_push_checked_at');
            }
            if (! Schema::hasColumn('order_fulfillment_trackings', 'channel_pushed_at')) {
                $table->timestamp('channel_pushed_at')->nullable()->after('shopify_push_message');
            }
            if (! Schema::hasColumn('order_fulfillment_trackings', 'channel_push_attempts')) {
                $table->unsignedTinyInteger('channel_push_attempts')->default(0)->after('channel_pushed_at');
            }
            if (! Schema::hasColumn('order_fulfillment_trackings', 'channel_push_message')) {
                $table->string('channel_push_message', 255)->nullable()->after('channel_push_attempts');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_fulfillment_trackings')) {
            return;
        }

        Schema::table('order_fulfillment_trackings', function (Blueprint $table) {
            foreach ([
                'shopify_order_id', 'shopify_fulfilled_at', 'shopify_push_attempts', 'shopify_push_checked_at',
                'shopify_push_message', 'channel_pushed_at', 'channel_push_attempts', 'channel_push_message',
            ] as $column) {
                if (Schema::hasColumn('order_fulfillment_trackings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
