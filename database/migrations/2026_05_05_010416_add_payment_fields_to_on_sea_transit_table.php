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
        Schema::table('on_sea_transit', function (Blueprint $table) {
            $table->decimal('paid', 15, 2)->nullable()->after('invoice_value');
            $table->decimal('balance', 15, 2)->nullable()->after('paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('on_sea_transit', function (Blueprint $table) {
            $table->dropColumn(['paid', 'balance']);
        });
    }
};
