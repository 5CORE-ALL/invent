<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reverb_order_metrics', function (Blueprint $table) {
            $table->timestamp('order_paid_at')->nullable()->after('order_date');
        });
    }

    public function down(): void
    {
        Schema::table('reverb_order_metrics', function (Blueprint $table) {
            $table->dropColumn('order_paid_at');
        });
    }
};
