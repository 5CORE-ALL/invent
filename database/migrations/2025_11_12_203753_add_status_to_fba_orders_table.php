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
        if (! Schema::hasTable('fba_orders')) {
            return;
        }

        if (! Schema::hasColumn('fba_orders', 'status')) {
            Schema::table('fba_orders', function (Blueprint $table) {
                $table->string('status')->nullable();
            });
        }
        if (! Schema::hasColumn('fba_orders', 'seller_sku')) {
            Schema::table('fba_orders', function (Blueprint $table) {
                $table->string('seller_sku')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fba_orders')) {
            return;
        }

        $columns = ['status', 'seller_sku'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('fba_orders', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('fba_orders', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
