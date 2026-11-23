<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The page also creates this table the first time a score moves, so a deploy without migrate still works. */
    public function up(): void
    {
        if (Schema::hasTable('lqs_shopify_rating_moves')) {
            return;
        }
        Schema::create('lqs_shopify_rating_moves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_product_id')->index();
            $table->string('sku', 191)->nullable();
            $table->string('kind', 16);
            $table->string('from_rating', 16);
            $table->string('to_rating', 16);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lqs_shopify_rating_moves');
    }
};
