<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_masters')) {
            return;
        }

        Schema::table('category_masters', function (Blueprint $table) {
            // The channel's own category / browse-node ID for each name, filled by Pull.
            // Push needs the ID; it is cleared when someone retypes the name.
            if (! Schema::hasColumn('category_masters', 'category_ebay_id')) {
                $table->string('category_ebay_id', 64)->nullable()->after('category_ebay');
            }
            if (! Schema::hasColumn('category_masters', 'category_amz_id')) {
                $table->string('category_amz_id', 64)->nullable()->after('category_amz');
            }
            if (! Schema::hasColumn('category_masters', 'category_temu_id')) {
                $table->string('category_temu_id', 64)->nullable()->after('category_temu');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('category_masters')) {
            return;
        }

        Schema::table('category_masters', function (Blueprint $table) {
            foreach (['category_ebay_id', 'category_amz_id', 'category_temu_id'] as $column) {
                if (Schema::hasColumn('category_masters', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
