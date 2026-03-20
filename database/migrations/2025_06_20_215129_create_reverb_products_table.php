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
        // Agar table already exists to column modify karo
        if (Schema::hasTable('reverb_products')) {
            DB::statement("ALTER TABLE `reverb_products` MODIFY `sku` VARCHAR(255)");
        } else {
            // Nayi table create
            Schema::create('reverb_products', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->unique()->nullable();
                $table->integer('r_l30')->nullable();
                $table->integer('r_l60')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->integer('views')->nullable();
                $table->string('reverb_listing_id')->nullable();
                $table->string('listing_state')->nullable();
                $table->integer('remaining_inventory')->nullable();
                $table->string('bump_bid')->nullable();
                $table->string('product_title')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reverb_products');
    }
};
