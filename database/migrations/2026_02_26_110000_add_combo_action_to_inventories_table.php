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

        if (Schema::hasColumn('inventories', 'combo_action')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'action')) {
                $table->string('combo_action')->nullable()->after('action');
            } else {
                $table->string('combo_action')->nullable();
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

        if (! Schema::hasColumn('inventories', 'combo_action')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('combo_action');
        });
    }
};
