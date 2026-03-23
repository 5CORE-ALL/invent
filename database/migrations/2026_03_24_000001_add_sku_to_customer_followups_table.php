<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_followups')) {
            return;
        }
        if (Schema::hasColumn('customer_followups', 'sku')) {
            return;
        }
        Schema::table('customer_followups', function (Blueprint $table) {
            $table->string('sku', 128)->nullable()->after('order_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_followups') || !Schema::hasColumn('customer_followups', 'sku')) {
            return;
        }
        Schema::table('customer_followups', function (Blueprint $table) {
            $table->dropColumn('sku');
        });
    }
};
