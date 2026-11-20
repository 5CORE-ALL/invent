<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time data fix: every PO gets an Advance & Payments row, and the archive
     * state of POs and payment rows is made identical.
     */
    public function up(): void
    {
        if (! Schema::hasTable('advance_payments')
            || ! Schema::hasTable('purchase_orders')
            || ! Schema::hasColumn('advance_payments', 'is_archived')
            || ! Schema::hasColumn('purchase_orders', 'is_archived')) {
            return;
        }

        Artisan::call('advance-payments:sync-from-pos');
    }

    public function down(): void
    {
        // data fix only
    }
};
