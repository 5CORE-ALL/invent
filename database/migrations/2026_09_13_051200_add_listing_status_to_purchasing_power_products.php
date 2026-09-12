<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchasing_power_products')) {
            return;
        }
        if (Schema::hasColumn('purchasing_power_products', 'listing_status')) {
            return;
        }

        Schema::table('purchasing_power_products', function (Blueprint $table) {
            $table->string('listing_status', 32)->nullable()->after('stock');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchasing_power_products')) {
            return;
        }
        if (! Schema::hasColumn('purchasing_power_products', 'listing_status')) {
            return;
        }

        Schema::table('purchasing_power_products', function (Blueprint $table) {
            $table->dropColumn('listing_status');
        });
    }
};
