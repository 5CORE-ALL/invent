<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amazon_datasheets', function (Blueprint $table) {
            $table->decimal('price_lmpa', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('amazon_datasheets', function (Blueprint $table) {
            $table->dropColumn('price_lmpa');
        });
    }
};
