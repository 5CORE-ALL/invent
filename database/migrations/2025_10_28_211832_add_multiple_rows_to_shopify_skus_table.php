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

        if (! Schema::hasColumn('shopify_skus', 'available_to_sell')) {
            Schema::table('shopify_skus', function (Blueprint $table) {
                $table->integer('available_to_sell')->default(0)->after('image_src');
            });
        }
        if (! Schema::hasColumn('shopify_skus', 'committed')) {
            Schema::table('shopify_skus', function (Blueprint $table) {
                $table->integer('committed')->default(0)->after('available_to_sell');
            });
        }
        if (! Schema::hasColumn('shopify_skus', 'on_hand')) {
            Schema::table('shopify_skus', function (Blueprint $table) {
                $table->integer('on_hand')->default(0)->after('committed');
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

        $columns = ['available_to_sell', 'committed', 'on_hand'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('shopify_skus', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('shopify_skus', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
