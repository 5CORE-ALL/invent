<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Latest item specifics actually pushed to a marketplace, keyed so another
 * marketplace can reuse the same SKU values without asking again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('backstage_specifications')) {
            return;
        }

        Schema::create('backstage_specifications', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 255);
            $table->string('marketplace', 64);
            $table->string('spec_key', 255);
            $table->string('spec_key_norm', 255);
            $table->text('spec_value');
            $table->string('category')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();

            $table->unique(['sku', 'marketplace', 'spec_key_norm'], 'backstage_specs_sku_market_key');
            $table->index(['sku', 'spec_key_norm'], 'backstage_specs_sku_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backstage_specifications');
    }
};
