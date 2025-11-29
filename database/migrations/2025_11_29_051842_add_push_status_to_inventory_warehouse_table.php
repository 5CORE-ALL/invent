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
        Schema::table('inventory_warehouse', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_warehouse', 'push_status')) {
                $table->string('push_status')->nullable()->after('pushed')->default('pending'); // 'success', 'failed', 'pending'
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_warehouse', function (Blueprint $table) {
            $table->dropColumn('push_status');
        });
    }
};
