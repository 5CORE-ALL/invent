<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_fulfillment_trackings')) {
            return;
        }

        // Empty results were treated as final for an hour, so a number that
        // already exists on Shopify stayed blank. Look those rows up again.
        DB::table('order_fulfillment_trackings')
            ->where(function ($q) {
                $q->whereNull('tracking_number')->orWhere('tracking_number', '');
            })
            ->where(function ($q) {
                $q->whereNull('source')->orWhere('source', '!=', 'manual');
            })
            ->update(['checked_at' => null]);
    }

    public function down(): void
    {
    }
};
