<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders_on_hold_options') || Schema::hasColumn('orders_on_hold_options', 'no_options')) {
            return;
        }
        Schema::table('orders_on_hold_options', function (Blueprint $table) {
            $table->boolean('no_options')->default(false)->after('upgrade_skus');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders_on_hold_options') || ! Schema::hasColumn('orders_on_hold_options', 'no_options')) {
            return;
        }
        Schema::table('orders_on_hold_options', function (Blueprint $table) {
            $table->dropColumn('no_options');
        });
    }
};
