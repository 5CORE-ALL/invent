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
        if (! Schema::hasTable('rfq_forms')) {
            return;
        }

        $afterFields = Schema::hasColumn('rfq_forms', 'fields');

        if (! Schema::hasColumn('rfq_forms', 'dimension_inner')) {
            Schema::table('rfq_forms', function (Blueprint $table) use ($afterFields): void {
                if ($afterFields) {
                    $table->string('dimension_inner')->after('fields');
                } else {
                    $table->string('dimension_inner');
                }
            });
        }
        if (! Schema::hasColumn('rfq_forms', 'product_dimension')) {
            Schema::table('rfq_forms', function (Blueprint $table) use ($afterFields): void {
                if ($afterFields) {
                    $table->string('product_dimension')->after('fields');
                } else {
                    $table->string('product_dimension');
                }
            });
        }
        if (! Schema::hasColumn('rfq_forms', 'package_dimension')) {
            Schema::table('rfq_forms', function (Blueprint $table) use ($afterFields): void {
                if ($afterFields) {
                    $table->string('package_dimension')->after('fields');
                } else {
                    $table->string('package_dimension');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('rfq_forms')) {
            return;
        }

        $cols = array_values(array_filter(
            ['dimension_inner', 'product_dimension', 'package_dimension'],
            fn (string $c): bool => Schema::hasColumn('rfq_forms', $c)
        ));

        if ($cols === []) {
            return;
        }

        Schema::table('rfq_forms', function (Blueprint $table) use ($cols): void {
            $table->dropColumn($cols);
        });
    }
};
