<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (! Schema::hasColumn('inventories', 'bin_id')) {
                $table->foreignId('bin_id')->nullable()->after('warehouse_id')->constrained('bins')->nullOnDelete();
            }
            if (! Schema::hasColumn('inventories', 'pick_locked_qty')) {
                $table->unsignedInteger('pick_locked_qty')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'bin_id')) {
                $table->dropForeign(['bin_id']);
                $table->dropColumn('bin_id');
            }
            if (Schema::hasColumn('inventories', 'pick_locked_qty')) {
                $table->dropColumn('pick_locked_qty');
            }
        });
    }
};
