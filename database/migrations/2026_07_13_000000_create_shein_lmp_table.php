<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the Shein LMP (lowest market price) research data imported from
 * sheinlmp.txt. Each internal SKU can have up to four competitor
 * price/URL pairs. Rows marked "nf" in the source (no competitor found)
 * are stored with `is_not_found` = true and null prices/urls.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shein_lmp')) {
            return;
        }

        Schema::create('shein_lmp', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->boolean('is_not_found')->default(false);

            $table->decimal('price_1', 10, 2)->nullable();
            $table->text('url_1')->nullable();
            $table->decimal('price_2', 10, 2)->nullable();
            $table->text('url_2')->nullable();
            $table->decimal('price_3', 10, 2)->nullable();
            $table->text('url_3')->nullable();
            $table->decimal('price_4', 10, 2)->nullable();
            $table->text('url_4')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shein_lmp');
    }
};
