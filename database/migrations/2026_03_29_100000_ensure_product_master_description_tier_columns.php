<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures product_master has tiered description columns for Description Master.
 * Idempotent: safe if a prior migration partially ran or the DB was restored without these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) {
            if (! Schema::hasColumn('product_master', 'description_1500')) {
                $table->text('description_1500')->nullable();
            }
            if (! Schema::hasColumn('product_master', 'description_1000')) {
                $table->text('description_1000')->nullable();
            }
            if (! Schema::hasColumn('product_master', 'description_800')) {
                $table->text('description_800')->nullable();
            }
            if (! Schema::hasColumn('product_master', 'description_600')) {
                $table->text('description_600')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) {
            foreach (['description_1500', 'description_1000', 'description_800', 'description_600'] as $col) {
                if (Schema::hasColumn('product_master', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
