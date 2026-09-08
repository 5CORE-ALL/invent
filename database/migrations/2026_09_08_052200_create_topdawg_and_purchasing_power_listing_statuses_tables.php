<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('topdawg_listing_statuses')) {
            Schema::create('topdawg_listing_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->index();
                $table->json('value');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('purchasing_power_listing_statuses')) {
            Schema::create('purchasing_power_listing_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->index();
                $table->json('value');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('topdawg_listing_statuses');
        Schema::dropIfExists('purchasing_power_listing_statuses');
    }
};
