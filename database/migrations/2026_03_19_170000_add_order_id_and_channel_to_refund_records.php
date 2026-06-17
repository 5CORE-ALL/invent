<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_records', function (Blueprint $table) {
            if (!Schema::hasColumn('refund_records', 'order_id')) {
                $table->string('order_id', 30)->nullable()->after('supplier_id');
            }
            if (!Schema::hasColumn('refund_records', 'channel_master_id')) {
                $table->unsignedBigInteger('channel_master_id')->nullable()->after('order_id');
                $table->index('channel_master_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('refund_records', function (Blueprint $table) {
            if (Schema::hasColumn('refund_records', 'channel_master_id')) {
                $table->dropIndex(['channel_master_id']);
                $table->dropColumn('channel_master_id');
            }
            if (Schema::hasColumn('refund_records', 'order_id')) {
                $table->dropColumn('order_id');
            }
        });
    }
};
