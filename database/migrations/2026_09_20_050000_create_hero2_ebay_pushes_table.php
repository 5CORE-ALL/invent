<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hero2_ebay_pushes')) {
            return;
        }

        Schema::create('hero2_ebay_pushes', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191);
            $table->string('account', 16);
            $table->string('image_url', 2048)->nullable();
            $table->string('item_id', 64)->nullable();
            $table->string('variation_value', 191)->nullable();
            $table->boolean('is_variation')->default(false);
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();
            $table->unique(['sku', 'account']);
            $table->index('account');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero2_ebay_pushes');
    }
};
