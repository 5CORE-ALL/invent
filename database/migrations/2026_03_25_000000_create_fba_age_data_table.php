<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fba_age_data')) {
            return;
        }

        Schema::create('fba_age_data', function (Blueprint $table) {
            $table->id();

            // ── Identifiers ───────────────────────────────────────────────────
            $table->date('snapshot_date')->nullable();
            $table->string('sku')->unique();
            $table->string('fnsku')->nullable();
            $table->string('asin')->nullable();
            $table->string('product_name')->nullable();
            $table->string('condition')->nullable();
            $table->string('marketplace')->nullable();
            $table->string('currency', 10)->nullable();

            // ── Current stock ─────────────────────────────────────────────────
            $table->integer('available')->default(0);
            $table->integer('pending_removal_quantity')->default(0);
            $table->integer('inbound_quantity')->default(0);
            $table->integer('inbound_working')->default(0);
            $table->integer('inbound_shipped')->default(0);
            $table->integer('inbound_received')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('unfulfillable_quantity')->default(0);

            // ── Age buckets (coarse) ──────────────────────────────────────────
            $table->integer('inv_age_0_to_90_days')->default(0);
            $table->integer('inv_age_91_to_180_days')->default(0);
            $table->integer('inv_age_181_to_270_days')->default(0);
            $table->integer('inv_age_271_to_365_days')->default(0);
            $table->integer('inv_age_366_to_455_days')->default(0);
            $table->integer('inv_age_456_plus_days')->default(0);

            // ── Age buckets (fine-grained) ────────────────────────────────────
            $table->integer('inv_age_0_to_30_days')->default(0);
            $table->integer('inv_age_31_to_60_days')->default(0);
            $table->integer('inv_age_61_to_90_days')->default(0);
            $table->integer('inv_age_181_to_330_days')->default(0);
            $table->integer('inv_age_331_to_365_days')->default(0);

            // ── Aged Inventory Surcharge (AIS) projections ────────────────────
            $table->integer('ais_qty_181_210')->nullable();
            $table->decimal('ais_est_181_210', 10, 2)->nullable();
            $table->integer('ais_qty_211_240')->nullable();
            $table->decimal('ais_est_211_240', 10, 2)->nullable();
            $table->integer('ais_qty_241_270')->nullable();
            $table->decimal('ais_est_241_270', 10, 2)->nullable();
            $table->integer('ais_qty_271_300')->nullable();
            $table->decimal('ais_est_271_300', 10, 2)->nullable();
            $table->integer('ais_qty_301_330')->nullable();
            $table->decimal('ais_est_301_330', 10, 2)->nullable();
            $table->integer('ais_qty_331_365')->nullable();
            $table->decimal('ais_est_331_365', 10, 2)->nullable();
            $table->integer('ais_qty_366_455')->nullable();
            $table->decimal('ais_est_366_455', 10, 2)->nullable();
            $table->integer('ais_qty_456_plus')->nullable();
            $table->decimal('ais_est_456_plus', 10, 2)->nullable();

            // ── Sales velocity ────────────────────────────────────────────────
            $table->integer('units_shipped_t7')->default(0);
            $table->integer('units_shipped_t30')->default(0);
            $table->integer('units_shipped_t60')->default(0);
            $table->integer('units_shipped_t90')->default(0);
            $table->decimal('sales_shipped_last_7_days', 12, 2)->nullable();
            $table->decimal('sales_shipped_last_30_days', 12, 2)->nullable();
            $table->decimal('sales_shipped_last_60_days', 12, 2)->nullable();
            $table->decimal('sales_shipped_last_90_days', 12, 2)->nullable();

            // ── Pricing ───────────────────────────────────────────────────────
            $table->decimal('your_price', 10, 2)->nullable();
            $table->decimal('sales_price', 10, 2)->nullable();
            $table->decimal('lowest_price_new_plus_shipping', 10, 2)->nullable();
            $table->decimal('lowest_price_used', 10, 2)->nullable();
            $table->decimal('featuredoffer_price', 10, 2)->nullable();

            // ── Health & recommendations ──────────────────────────────────────
            $table->string('health_status')->nullable();
            $table->string('alert')->nullable();
            $table->string('recommended_action')->nullable();
            $table->integer('recommended_removal_quantity')->default(0);
            $table->decimal('recommended_sales_price', 10, 2)->nullable();
            $table->integer('recommended_sale_duration_days')->nullable();
            $table->decimal('estimated_cost_savings', 10, 2)->nullable();
            $table->boolean('no_sale_last_6_months')->default(false);

            // ── Supply / days metrics ─────────────────────────────────────────
            $table->decimal('sell_through', 8, 4)->nullable();
            $table->integer('days_of_supply')->nullable();
            $table->integer('total_days_of_supply')->nullable();
            $table->integer('estimated_excess_quantity')->nullable();
            $table->decimal('weeks_of_cover_t30', 8, 2)->nullable();
            $table->decimal('weeks_of_cover_t90', 8, 2)->nullable();
            $table->decimal('historical_days_of_supply', 8, 2)->nullable();
            $table->decimal('short_term_days_of_supply', 8, 2)->nullable();
            $table->decimal('long_term_days_of_supply', 8, 2)->nullable();
            $table->integer('fba_minimum_inventory_level')->nullable();
            $table->date('inventory_age_snapshot_date')->nullable();

            // ── Storage / volume ──────────────────────────────────────────────
            $table->string('storage_type')->nullable();
            $table->decimal('storage_volume', 12, 6)->nullable();
            $table->decimal('item_volume', 12, 6)->nullable();
            $table->string('volume_unit_measurement')->nullable();

            // ── Fees ──────────────────────────────────────────────────────────
            $table->decimal('estimated_storage_cost_next_month', 10, 2)->nullable();

            // ── Misc ──────────────────────────────────────────────────────────
            $table->integer('sales_rank')->nullable();
            $table->string('product_group')->nullable();

            $table->timestamps();

            $table->index('asin');
            $table->index('health_status');
            $table->index('recommended_action');
            $table->index('snapshot_date');
            $table->index('inv_age_181_to_270_days');
            $table->index('inv_age_271_to_365_days');
            $table->index('inv_age_366_to_455_days');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fba_age_data');
    }
};
