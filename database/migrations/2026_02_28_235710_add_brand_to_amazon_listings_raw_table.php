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
        $tableName = 'amazon_listings_raw';

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'brand')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'manufacturer')) {
                $table->string('brand')->nullable()->after('manufacturer');
            } else {
                $table->string('brand')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'amazon_listings_raw';

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'brand')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('brand');
        });
    }
};
