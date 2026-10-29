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
            // CAT-5C: the category 5Core files the SKU under. Typed by hand, never pulled or pushed.
            if (! Schema::hasColumn('category_masters', 'category_5c')) {
                $table->string('category_5c', 255)->nullable();
            }
            if (! Schema::hasColumn('category_masters', 'category_5c_id')) {
                $table->string('category_5c_id', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('category_masters')) {
            return;
        }

        Schema::table('category_masters', function (Blueprint $table) {
            foreach (['category_5c', 'category_5c_id'] as $column) {
                if (Schema::hasColumn('category_masters', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
