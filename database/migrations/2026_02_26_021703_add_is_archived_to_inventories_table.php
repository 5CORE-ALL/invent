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

        if (Schema::hasColumn('inventories', 'is_archived')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'type')) {
                $table->boolean('is_archived')->default(false)->after('type');
            } else {
                $table->boolean('is_archived')->default(false);
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

        if (! Schema::hasColumn('inventories', 'is_archived')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('is_archived');
        });
    }
};
