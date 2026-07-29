<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('temu_metrics')) {
            return;
        }

        if (! Schema::hasColumn('temu_metrics', 'quantity')) {
            Schema::table('temu_metrics', function (Blueprint $table) {
                $table->integer('quantity')->default(0)->after('base_price');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('temu_metrics')) {
            return;
        }

        if (Schema::hasColumn('temu_metrics', 'quantity')) {
            Schema::table('temu_metrics', function (Blueprint $table) {
                $table->dropColumn('quantity');
            });
        }
    }
};
