<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('walmart_daily_data', function (Blueprint $table) {
            // Customer contact
            $table->string('customer_phone', 50)->nullable()->after('customer_name');
            $table->string('customer_email', 255)->nullable()->after('customer_phone');
            
            // Order type info
            $table->string('order_type', 50)->nullable()->after('order_date');
            $table->string('mart_id', 20)->nullable()->after('order_type');
            $table->boolean('is_replacement')->default(false)->after('mart_id');
            $table->boolean('is_premium_order')->default(false)->after('is_replacement');
            $table->string('original_customer_order_id', 50)->nullable()->after('is_premium_order');
            $table->string('replacement_order_id', 50)->nullable()->after('original_customer_order_id');
            $table->string('seller_order_id', 50)->nullable()->after('replacement_order_id');
            
            // Additional charges
            $table->decimal('shipping_charge', 10, 2)->nullable()->after('tax_amount');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('shipping_charge');
            $table->decimal('fee_amount', 10, 2)->nullable()->after('discount_amount');
            
            // Refund/Cancel info
            $table->string('cancellation_reason', 255)->nullable()->after('status_date');
            $table->decimal('refund_amount', 10, 2)->nullable()->after('cancellation_reason');
            $table->string('refund_reason', 255)->nullable()->after('refund_amount');
            
            // Additional shipping info
            $table->string('ship_method_code', 50)->nullable()->after('shipping_method');
            $table->string('pickup_location', 255)->nullable()->after('ship_node_name');
            
            // Line level info
            $table->string('upc', 50)->nullable()->after('sku');
            $table->string('gtin', 50)->nullable()->after('upc');
            $table->string('item_id', 50)->nullable()->after('gtin');
            
            // Seller/Partner info
            $table->string('partner_id', 50)->nullable()->after('pickup_location');
            
            // Status details (all statuses)
            $table->text('all_statuses_json')->nullable()->after('status');
            
            // Original line data
            $table->text('order_line_json')->nullable()->after('all_statuses_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('walmart_daily_data', function (Blueprint $table) {
            $table->dropColumn([
                'customer_phone',
                'customer_email',
                'order_type',
                'mart_id',
                'is_replacement',
                'is_premium_order',
                'original_customer_order_id',
                'replacement_order_id',
                'seller_order_id',
                'shipping_charge',
                'discount_amount',
                'fee_amount',
                'cancellation_reason',
                'refund_amount',
                'refund_reason',
                'ship_method_code',
                'pickup_location',
                'upc',
                'gtin',
                'item_id',
                'partner_id',
                'all_statuses_json',
                'order_line_json',
            ]);
        });
    }
};
