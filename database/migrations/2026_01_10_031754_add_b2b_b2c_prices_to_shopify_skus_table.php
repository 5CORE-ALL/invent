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
        if (! Schema::hasTable('shopify_skus')) {
            return;
        }

        if (! Schema::hasColumn('shopify_skus', 'b2b_price')) {
            Schema::table('shopify_skus', function (Blueprint $table) {
                $table->string('b2b_price', 191)->nullable()->after('price');
            });
        }
        if (! Schema::hasColumn('shopify_skus', 'b2c_price')) {
            Schema::table('shopify_skus', function (Blueprint $table) {
                $table->string('b2c_price', 191)->nullable()->after('b2b_price');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('shopify_skus')) {
            return;
        }

        $columns = ['b2b_price', 'b2c_price'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('shopify_skus', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('shopify_skus', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
