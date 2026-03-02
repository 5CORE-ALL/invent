<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reverb_order_metrics', function (Blueprint $table) {
            // Add shopify_order_id
            $table->string('shopify_order_id')->nullable()->after('order_number');
            
            // Add pushed_to_shopify_at
            $table->timestamp('pushed_to_shopify_at')->nullable()->after('shopify_order_id');
            
            // Add import_status
            $table->string('import_status', 32)->nullable()->after('pushed_to_shopify_at');
        });
    }

    public function down(): void
    {
        Schema::table('reverb_order_metrics', function (Blueprint $table) {
            $table->dropColumn(['shopify_order_id', 'pushed_to_shopify_at', 'import_status']);
        });
    }
};
