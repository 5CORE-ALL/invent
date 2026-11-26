<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History of how many orders are Pending (per site) on /order-fulfillment/pending.
 *
 *  kind = 'hourly' : one row per hourly refresh.
 *  kind = 'daily'  : the once-a-day record taken at 3:30 PM Ohio (America/New_York) time.
 *
 * snapshot_date is the Ohio calendar date, so a daily record is unique per date.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_fulfillment_pending_snapshots')) {
            return;
        }

        Schema::create('order_fulfillment_pending_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);
            $table->date('snapshot_date');
            $table->dateTime('captured_at');
            $table->unsignedInteger('total_orders')->default(0);
            // { "<site slug>": { "label": "eBay 3", "orders": 12 }, ... }
            $table->json('counts')->nullable();
            $table->timestamps();

            $table->index(['kind', 'captured_at']);
            $table->index(['kind', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_fulfillment_pending_snapshots');
    }
};
