<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopify_skus')) {
            return;
        }
        Schema::table('shopify_skus', function (Blueprint $table) {
            if (! Schema::hasColumn('shopify_skus', 'unavailable')) {
                $table->integer('unavailable')->default(0)->after('on_hand');
            }
        });
        Schema::table('shopify_skus', function (Blueprint $table) {
            if (! Schema::hasColumn('shopify_skus', 'incoming')) {
                $table->integer('incoming')->default(0)->after('unavailable');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shopify_skus')) {
            return;
        }
        Schema::table('shopify_skus', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('shopify_skus', 'unavailable') ? 'unavailable' : null,
                Schema::hasColumn('shopify_skus', 'incoming') ? 'incoming' : null,
            ]));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
