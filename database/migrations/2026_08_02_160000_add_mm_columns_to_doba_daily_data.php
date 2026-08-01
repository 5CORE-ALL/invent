<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('doba_daily_data')) {
            Schema::table('doba_daily_data', function (Blueprint $table) {
                if (! Schema::hasColumn('doba_daily_data', 'shopify_order_id')) {
                    $table->string('shopify_order_id')->nullable()->index();
                }
                if (! Schema::hasColumn('doba_daily_data', 'pushed_to_shopify_at')) {
                    $table->timestamp('pushed_to_shopify_at')->nullable();
                }
                if (! Schema::hasColumn('doba_daily_data', 'import_status')) {
                    $table->string('import_status')->nullable()->index();
                }
                if (! Schema::hasColumn('doba_daily_data', 'raw_payload')) {
                    $table->json('raw_payload')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('doba_daily_data')) {
            Schema::table('doba_daily_data', function (Blueprint $table) {
                foreach (['shopify_order_id', 'pushed_to_shopify_at', 'import_status', 'raw_payload'] as $col) {
                    if (Schema::hasColumn('doba_daily_data', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
