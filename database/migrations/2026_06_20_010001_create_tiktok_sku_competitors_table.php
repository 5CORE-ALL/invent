<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop a previous half-created table from a failed migration run (the
        // composite unique index can fail on utf8mb4 when columns are too
        // wide, leaving the table without its unique). This is only ever
        // destructive in the partial-create case — once the migration is
        // recorded as `Ran`, Laravel never invokes this method again.
        Schema::dropIfExists('tiktok_sku_competitors');

        Schema::create('tiktok_sku_competitors', function (Blueprint $table) {
            $table->id();
            // Shortened for utf8mb4 composite unique index (4 bytes/char).
            // Sum of indexed widths must stay under MySQL's 3072-byte limit.
            //   sku(191) + product_id(64) + marketplace(50) + region(8) = 313 chars
            //   × 4 bytes (utf8mb4) = 1252 bytes  -> well under 3072
            $table->string('sku', 191)->index();
            $table->string('product_id', 64)->index();
            $table->string('marketplace', 50)->default('tiktok');
            $table->string('region', 8)->default('US');
            $table->text('product_title')->nullable();
            $table->text('product_link')->nullable();
            $table->string('image', 1024)->nullable();
            $table->string('seller_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('min_price', 10, 2)->nullable();
            $table->decimal('max_price', 10, 2)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('reviews')->nullable();
            $table->integer('sold_count')->nullable();
            $table->timestamps();

            $table->unique(['sku', 'product_id', 'marketplace', 'region'], 'tiktok_sku_comp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_sku_competitors');
    }
};
