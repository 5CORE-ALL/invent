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

        Schema::table('temu_metrics', function (Blueprint $table) {
            if (! Schema::hasColumn('temu_metrics', 'recommended_base_price')) {
                $table->decimal('recommended_base_price', 12, 2)->nullable()->after('base_price');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('temu_metrics')) {
            return;
        }

        Schema::table('temu_metrics', function (Blueprint $table) {
            if (Schema::hasColumn('temu_metrics', 'recommended_base_price')) {
                $table->dropColumn('recommended_base_price');
            }
        });
    }
};
