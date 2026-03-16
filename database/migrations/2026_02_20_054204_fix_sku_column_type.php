<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SKU column ko VARCHAR(255) banao
        DB::statement("ALTER TABLE `reverb_products` MODIFY `sku` VARCHAR(255)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Wapas original type par (agar revert karna ho)
        DB::statement("ALTER TABLE `reverb_products` MODIFY `sku` TEXT");
    }
};