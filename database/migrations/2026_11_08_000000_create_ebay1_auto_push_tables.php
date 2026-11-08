<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ebay1 Data auto push:
     *  - ebay1_settings: the on/off switch (key "auto_push").
     *  - ebay1_pending_specifics: item specifics edited by hand that were not pushed to eBay yet.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ebay1_settings')) {
            Schema::create('ebay1_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ebay1_pending_specifics')) {
            Schema::create('ebay1_pending_specifics', function (Blueprint $table) {
                $table->id();
                $table->string('sku', 191);
                $table->string('name_key', 191);
                $table->string('name', 191);
                $table->timestamps();
                $table->unique(['sku', 'name_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay1_pending_specifics');
        Schema::dropIfExists('ebay1_settings');
    }
};
