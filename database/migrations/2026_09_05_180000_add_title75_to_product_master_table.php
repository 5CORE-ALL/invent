<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }

        if (! Schema::hasColumn('product_master', 'title75')) {
            Schema::table('product_master', function (Blueprint $table) {
                $after = Schema::hasColumn('product_master', 'title80') ? 'title80' : 'title100';
                $table->text('title75')->nullable()->after($after);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_master') || ! Schema::hasColumn('product_master', 'title75')) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) {
            $table->dropColumn('title75');
        });
    }
};
