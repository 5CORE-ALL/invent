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
        if (! Schema::hasTable('walmart_daily_data')) {
            return;
        }

        $this->addIfMissing('walmart_daily_data', 'customer_phone', fn (Blueprint $table) => $table->string('customer_phone', 50)->nullable()->after('customer_name'));
        $this->addIfMissing('walmart_daily_data', 'customer_email', fn (Blueprint $table) => $table->string('customer_email', 255)->nullable()->after('customer_phone'));
        $this->addIfMissing('walmart_daily_data', 'order_type', fn (Blueprint $table) => $table->string('order_type', 50)->nullable()->after('order_date'));
        $this->addIfMissing('walmart_daily_data', 'mart_id', fn (Blueprint $table) => $table->string('mart_id', 20)->nullable()->after('order_type'));
        $this->addIfMissing('walmart_daily_data', 'is_replacement', fn (Blueprint $table) => $table->boolean('is_replacement')->default(false)->after('mart_id'));
        $this->addIfMissing('walmart_daily_data', 'is_premium_order', fn (Blueprint $table) => $table->boolean('is_premium_order')->default(false)->after('is_replacement'));
        $this->addIfMissing('walmart_daily_data', 'original_customer_order_id', fn (Blueprint $table) => $table->string('original_customer_order_id', 50)->nullable()->after('is_premium_order'));
        $this->addIfMissing('walmart_daily_data', 'replacement_order_id', fn (Blueprint $table) => $table->string('replacement_order_id', 50)->nullable()->after('original_customer_order_id'));
        $this->addIfMissing('walmart_daily_data', 'seller_order_id', fn (Blueprint $table) => $table->string('seller_order_id', 50)->nullable()->after('replacement_order_id'));
        $this->addIfMissing('walmart_daily_data', 'shipping_charge', fn (Blueprint $table) => $table->decimal('shipping_charge', 10, 2)->nullable()->after('tax_amount'));
        $this->addIfMissing('walmart_daily_data', 'discount_amount', fn (Blueprint $table) => $table->decimal('discount_amount', 10, 2)->nullable()->after('shipping_charge'));
        $this->addIfMissing('walmart_daily_data', 'fee_amount', fn (Blueprint $table) => $table->decimal('fee_amount', 10, 2)->nullable()->after('discount_amount'));
        $this->addIfMissing('walmart_daily_data', 'cancellation_reason', fn (Blueprint $table) => $table->string('cancellation_reason', 255)->nullable()->after('status_date'));
        $this->addIfMissing('walmart_daily_data', 'refund_amount', fn (Blueprint $table) => $table->decimal('refund_amount', 10, 2)->nullable()->after('cancellation_reason'));
        $this->addIfMissing('walmart_daily_data', 'refund_reason', fn (Blueprint $table) => $table->string('refund_reason', 255)->nullable()->after('refund_amount'));
        $this->addIfMissing('walmart_daily_data', 'ship_method_code', fn (Blueprint $table) => $table->string('ship_method_code', 50)->nullable()->after('shipping_method'));
        $this->addIfMissing('walmart_daily_data', 'pickup_location', fn (Blueprint $table) => $table->string('pickup_location', 255)->nullable()->after('ship_node_name'));
        $this->addIfMissing('walmart_daily_data', 'upc', fn (Blueprint $table) => $table->string('upc', 50)->nullable()->after('sku'));
        $this->addIfMissing('walmart_daily_data', 'gtin', fn (Blueprint $table) => $table->string('gtin', 50)->nullable()->after('upc'));
        $this->addIfMissing('walmart_daily_data', 'item_id', fn (Blueprint $table) => $table->string('item_id', 50)->nullable()->after('gtin'));
        $this->addIfMissing('walmart_daily_data', 'partner_id', fn (Blueprint $table) => $table->string('partner_id', 50)->nullable()->after('pickup_location'));
        $this->addIfMissing('walmart_daily_data', 'all_statuses_json', fn (Blueprint $table) => $table->text('all_statuses_json')->nullable()->after('status'));
        $this->addIfMissing('walmart_daily_data', 'order_line_json', fn (Blueprint $table) => $table->text('order_line_json')->nullable()->after('all_statuses_json'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('walmart_daily_data')) {
            return;
        }

        $columns = [
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
        ];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('walmart_daily_data', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('walmart_daily_data', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }

    private function addIfMissing(string $tableName, string $column, \Closure $callback): void
    {
        if (Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($callback): void {
            $callback($table);
        });
    }
};
