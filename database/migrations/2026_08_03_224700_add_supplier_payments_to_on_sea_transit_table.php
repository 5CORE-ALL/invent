<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('on_sea_transit', function (Blueprint $table) {
            if (!Schema::hasColumn('on_sea_transit', 'supplier_payments')) {
                $table->json('supplier_payments')->nullable()->after('balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('on_sea_transit', function (Blueprint $table) {
            if (Schema::hasColumn('on_sea_transit', 'supplier_payments')) {
                $table->dropColumn('supplier_payments');
            }
        });
    }
};
