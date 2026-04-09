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
        if (! Schema::hasTable('shein_sheet_data')) {
            return;
        }

        if (! Schema::hasColumn('shein_sheet_data', 'shopify_sheinl30')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->integer('shopify_sheinl30')->nullable();
            });
        }
        if (! Schema::hasColumn('shein_sheet_data', 'shopify_sheinl60')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->integer('shopify_sheinl60')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('shein_sheet_data')) {
            return;
        }

        $cols = array_values(array_filter(
            ['shopify_sheinl30', 'shopify_sheinl60'],
            fn (string $c): bool => Schema::hasColumn('shein_sheet_data', $c)
        ));

        if ($cols === []) {
            return;
        }

        Schema::table('shein_sheet_data', function (Blueprint $table) use ($cols): void {
            $table->dropColumn($cols);
        });
    }
};
