<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pls_products') || Schema::hasColumn('pls_products', 'views')) {
            return;
        }

        Schema::table('pls_products', function (Blueprint $table) {
            $table->unsignedInteger('views')->default(0)->after('p_l60');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pls_products') || ! Schema::hasColumn('pls_products', 'views')) {
            return;
        }

        Schema::table('pls_products', function (Blueprint $table) {
            $table->dropColumn('views');
        });
    }
};
