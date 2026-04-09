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

        if (! Schema::hasColumn('product_master', 'sales')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->json('sales')->nullable()->after('Values');
            });
        }
        if (! Schema::hasColumn('product_master', 'views')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->json('views')->nullable()->after('sales');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }

        $columns = ['sales', 'views'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('product_master', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
