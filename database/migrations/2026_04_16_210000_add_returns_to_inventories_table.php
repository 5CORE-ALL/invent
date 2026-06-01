<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Incoming return label (default "returns"), shown in Incoming Return grid.
     */
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (! Schema::hasColumn('inventories', 'returns')) {
                $table->string('returns', 255)->nullable()->after('reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'returns')) {
                $table->dropColumn('returns');
            }
        });
    }
};
