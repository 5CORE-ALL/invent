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

        Schema::table('shopify_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('shopify_orders', 'financial_status')) {
                $table->string('financial_status', 64)->nullable()->index()->after('order_status');
            }
            if (! Schema::hasColumn('shopify_orders', 'fulfillment_status')) {
                $table->string('fulfillment_status', 64)->nullable()->index()->after('financial_status');
            }
            if (! Schema::hasColumn('shopify_orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->index()->after('fulfillment_status');
            }
            if (! Schema::hasColumn('shopify_orders', 'subtotal_price')) {
                $table->decimal('subtotal_price', 12, 2)->nullable()->after('total_price');
            }
            if (! Schema::hasColumn('shopify_orders', 'total_discounts')) {
                $table->decimal('total_discounts', 12, 2)->nullable()->after('subtotal_price');
            }
            if (! Schema::hasColumn('shopify_orders', 'total_tax')) {
                $table->decimal('total_tax', 12, 2)->nullable()->after('total_discounts');
            }
            if (! Schema::hasColumn('shopify_orders', 'shipping_price')) {
                $table->decimal('shipping_price', 12, 2)->nullable()->after('total_tax');
            }
            if (! Schema::hasColumn('shopify_orders', 'source_name')) {
                $table->string('source_name', 100)->nullable()->index()->after('currency');
            }
            if (! Schema::hasColumn('shopify_orders', 'source_identifier')) {
                $table->string('source_identifier')->nullable()->after('source_name');
            }
            if (! Schema::hasColumn('shopify_orders', 'landing_site')) {
                $table->string('landing_site')->nullable()->after('source_identifier');
            }
            if (! Schema::hasColumn('shopify_orders', 'referring_site')) {
                $table->string('referring_site')->nullable()->after('landing_site');
            }
            if (! Schema::hasColumn('shopify_orders', 'line_items_count')) {
                $table->unsignedInteger('line_items_count')->default(0)->after('referring_site');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shopify_orders')) {
            return;
        }

        Schema::table('shopify_orders', function (Blueprint $table) {
            foreach ([
                'line_items_count',
                'referring_site',
                'landing_site',
                'source_identifier',
                'source_name',
                'shipping_price',
                'total_tax',
                'total_discounts',
                'subtotal_price',
                'cancelled_at',
                'fulfillment_status',
                'financial_status',
            ] as $column) {
                if (Schema::hasColumn('shopify_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
