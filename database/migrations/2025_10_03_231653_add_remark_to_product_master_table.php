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
        if (! Schema::hasTable('product_master') || Schema::hasColumn('product_master', 'remark')) {
            return;
        }

        $afterColumn = null;
        if (Schema::hasColumn('product_master', 'Values')) {
            $afterColumn = 'Values';
        } elseif (Schema::hasColumn('product_master', 'values')) {
            $afterColumn = 'values';
        }

        Schema::table('product_master', function (Blueprint $table) use ($afterColumn): void {
            if ($afterColumn !== null) {
                $table->text('remark')->nullable()->after($afterColumn);
            } else {
                $table->text('remark')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('product_master') || ! Schema::hasColumn('product_master', 'remark')) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) {
            $table->dropColumn('remark');
        });
    }
};
