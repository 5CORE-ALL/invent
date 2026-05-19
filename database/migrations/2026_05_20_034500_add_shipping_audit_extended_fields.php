<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_audit_logs')) {
            return;
        }

        Schema::table('shipping_audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('shipping_audit_logs', 'cancelled_orders_not_shipped')) {
                $table->boolean('cancelled_orders_not_shipped')->default(false)->after('all_messages_cleared');
            }
            if (! Schema::hasColumn('shipping_audit_logs', 'required_weight_dimensions_declared')) {
                $table->boolean('required_weight_dimensions_declared')->default(false)->after('cancelled_orders_not_shipped');
            }
            if (! Schema::hasColumn('shipping_audit_logs', 'correct_lowest_label_cost_purchased')) {
                $table->boolean('correct_lowest_label_cost_purchased')->default(false)->after('required_weight_dimensions_declared');
            }
            if (! Schema::hasColumn('shipping_audit_logs', 'combined_shipment_message_sent')) {
                $table->boolean('combined_shipment_message_sent')->default(false)->after('correct_lowest_label_cost_purchased');
            }
            if (! Schema::hasColumn('shipping_audit_logs', 'split_shipment_message_tracking_updated')) {
                $table->boolean('split_shipment_message_tracking_updated')->default(false)->after('combined_shipment_message_sent');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipping_audit_logs')) {
            return;
        }

        Schema::table('shipping_audit_logs', function (Blueprint $table) {
            foreach ([
                'cancelled_orders_not_shipped',
                'required_weight_dimensions_declared',
                'correct_lowest_label_cost_purchased',
                'combined_shipment_message_sent',
                'split_shipment_message_tracking_updated',
            ] as $col) {
                if (Schema::hasColumn('shipping_audit_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
