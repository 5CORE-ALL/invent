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
            if (! Schema::hasColumn('temu_metrics', 'goods_summary')) {
                $table->text('goods_summary')->nullable()->after('goods_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('temu_metrics')) {
            return;
        }

        Schema::table('temu_metrics', function (Blueprint $table) {
            if (Schema::hasColumn('temu_metrics', 'goods_summary')) {
                $table->dropColumn('goods_summary');
            }
        });
    }
};
