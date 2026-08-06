<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_order_fulfillment_daily_data')) {
            return;
        }

        Schema::create('sales_order_fulfillment_daily_data', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date'); // Pacific calendar day (America/Los_Angeles)
            $table->json('summary_data');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('snapshot_date');
            $table->index('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_fulfillment_daily_data');
    }
};
