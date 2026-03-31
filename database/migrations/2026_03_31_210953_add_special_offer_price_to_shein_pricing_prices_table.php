<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shein_pricing_prices', function (Blueprint $table) {
            $table->decimal('special_offer_price', 12, 2)->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('shein_pricing_prices', function (Blueprint $table) {
            $table->dropColumn('special_offer_price');
        });
    }
};
