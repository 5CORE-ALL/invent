<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reverb_order_metrics')) {
            return;
        }

        if (Schema::hasColumn('reverb_order_metrics', 'import_status')) {
            return;
        }

        Schema::table('reverb_order_metrics', function (Blueprint $table) {
            if (Schema::hasColumn('reverb_order_metrics', 'pushed_to_shopify_at')) {
                $table->string('import_status', 32)->nullable()->after('pushed_to_shopify_at');
            } else {
                $table->string('import_status', 32)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reverb_order_metrics')) {
            return;
        }

        if (! Schema::hasColumn('reverb_order_metrics', 'import_status')) {
            return;
        }

        Schema::table('reverb_order_metrics', function (Blueprint $table) {
            $table->dropColumn('import_status');
        });
    }
};
