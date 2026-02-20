<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reverb_order_metrics', function (Blueprint $table) {
            $table->string('import_status', 32)->nullable()->after('pushed_to_shopify_at');
        });
    }

    public function down(): void
    {
        Schema::table('reverb_order_metrics', function (Blueprint $table) {
            $table->dropColumn('import_status');
        });
    }
};
