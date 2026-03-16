<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reverb_order_metrics', function (Blueprint $table) {
            $table->string('shopify_order_id')->nullable()->after('order_number');
            $table->timestamp('pushed_to_shopify_at')->nullable()->after('shopify_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('reverb_order_metrics', function (Blueprint $table) {
            $table->dropColumn(['shopify_order_id', 'pushed_to_shopify_at']);
        });
    }
};
