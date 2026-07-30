<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shein_metrics')) {
            return;
        }

        Schema::table('shein_metrics', function (Blueprint $table) {
            if (! Schema::hasColumn('shein_metrics', 'shein_sku_code')) {
                $table->string('shein_sku_code', 64)->nullable()->after('sku')->index();
            }
            if (! Schema::hasColumn('shein_metrics', 'sku_source')) {
                $table->string('sku_source', 32)->nullable()->after('shein_sku_code');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shein_metrics')) {
            return;
        }

        Schema::table('shein_metrics', function (Blueprint $table) {
            if (Schema::hasColumn('shein_metrics', 'sku_source')) {
                $table->dropColumn('sku_source');
            }
            if (Schema::hasColumn('shein_metrics', 'shein_sku_code')) {
                $table->dropColumn('shein_sku_code');
            }
        });
    }
};
