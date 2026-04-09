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

        if (! Schema::hasColumn('shein_sheet_data', 'sku')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->string('sku')->unique();
            });
        }
        if (! Schema::hasColumn('shein_sheet_data', 'price')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->decimal('price', 10, 2)->nullable();
            });
        }
        if (! Schema::hasColumn('shein_sheet_data', 'roi')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->decimal('roi', 10, 2)->nullable();
            });
        }
        if (! Schema::hasColumn('shein_sheet_data', 'l30')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->integer('l30')->nullable();
            });
        }
        if (! Schema::hasColumn('shein_sheet_data', 'buy_link')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->string('buy_link')->nullable();
            });
        }
        if (! Schema::hasColumn('shein_sheet_data', 's_link')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->string('s_link')->nullable();
            });
        }
        if (! Schema::hasColumn('shein_sheet_data', 'views_clicks')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->integer('views_clicks')->nullable();
            });
        }
        if (! Schema::hasColumn('shein_sheet_data', 'lmp')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->decimal('lmp', 10, 2)->nullable();
            });
        }
        if (! Schema::hasColumn('shein_sheet_data', 'link1')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->string('link1')->nullable();
            });
        }
        if (! Schema::hasColumn('shein_sheet_data', 'link2')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->string('link2')->nullable();
            });
        }
        if (! Schema::hasColumn('shein_sheet_data', 'link3')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->string('link3')->nullable();
            });
        }
        if (! Schema::hasColumn('shein_sheet_data', 'link4')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->string('link4')->nullable();
            });
        }
        if (! Schema::hasColumn('shein_sheet_data', 'link5')) {
            Schema::table('shein_sheet_data', function (Blueprint $table) {
                $table->string('link5')->nullable();
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
            ['sku', 'price', 'roi', 'l30', 'buy_link', 's_link', 'views_clicks', 'lmp', 'link1', 'link2', 'link3', 'link4', 'link5'],
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
