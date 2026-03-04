<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fix: Field 'id' doesn't have a default value - ensure id is AUTO_INCREMENT.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            // Ensure id is primary key and auto_increment (fix "Field 'id' doesn't have a default value")
            DB::statement('ALTER TABLE tasks MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting AUTO_INCREMENT is rarely needed; leave no-op.
    }
};
