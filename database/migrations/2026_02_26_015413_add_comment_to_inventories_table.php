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
        if (! Schema::hasTable('inventories')) {
            return;
        }

        if (Schema::hasColumn('inventories', 'comment')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'remarks')) {
                $table->string('comment', 80)->nullable()->after('remarks');
            } else {
                $table->string('comment', 80)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('inventories')) {
            return;
        }

        if (! Schema::hasColumn('inventories', 'comment')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('comment');
        });
    }
};
