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
        if (! Schema::hasTable('product_master')) {
            return;
        }
        if (Schema::hasColumn('product_master', 'product_description')) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) {
            $table->text('product_description')->nullable()->after('bullet5');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }
        if (! Schema::hasColumn('product_master', 'product_description')) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) {
            $table->dropColumn('product_description');
        });
    }
};
