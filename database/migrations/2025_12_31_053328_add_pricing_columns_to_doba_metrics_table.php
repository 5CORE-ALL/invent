<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('doba_metrics', function (Blueprint $table) {
            $table->decimal('self_pick_price', 10, 2)->nullable()->after('anticipated_income');
            $table->decimal('msrp', 10, 2)->nullable()->after('self_pick_price');
            $table->decimal('map', 10, 2)->nullable()->after('msrp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doba_metrics', function (Blueprint $table) {
            $table->dropColumn(['self_pick_price', 'msrp', 'map']);
        });
    }
};
