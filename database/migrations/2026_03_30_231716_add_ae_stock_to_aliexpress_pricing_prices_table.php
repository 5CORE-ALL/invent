<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aliexpress_pricing_prices', function (Blueprint $table) {
            $table->unsignedInteger('ae_stock')->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('aliexpress_pricing_prices', function (Blueprint $table) {
            $table->dropColumn('ae_stock');
        });
    }
};
