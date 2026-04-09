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

        if (! Schema::hasColumn('product_master', 'title150')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->text('title150')->nullable()->after('sku');
            });
        }
        if (! Schema::hasColumn('product_master', 'title100')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->text('title100')->nullable()->after('title150');
            });
        }
        if (! Schema::hasColumn('product_master', 'title80')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->text('title80')->nullable()->after('title100');
            });
        }
        if (! Schema::hasColumn('product_master', 'title60')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->text('title60')->nullable()->after('title80');
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

        $columns = ['title150', 'title100', 'title80', 'title60'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('product_master', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
