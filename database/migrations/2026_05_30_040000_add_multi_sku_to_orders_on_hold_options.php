<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders_on_hold_options')) {
            return;
        }
        Schema::table('orders_on_hold_options', function (Blueprint $table) {
            if (! Schema::hasColumn('orders_on_hold_options', 'variant_skus')) {
                $table->text('variant_skus')->nullable()->after('issue_id');
            }
            if (! Schema::hasColumn('orders_on_hold_options', 'upgrade_skus')) {
                $table->text('upgrade_skus')->nullable()->after('variant_skus');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders_on_hold_options')) {
            return;
        }
        Schema::table('orders_on_hold_options', function (Blueprint $table) {
            foreach (['variant_skus', 'upgrade_skus'] as $col) {
                if (Schema::hasColumn('orders_on_hold_options', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
