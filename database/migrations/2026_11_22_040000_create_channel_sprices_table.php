<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_sprices')) {
            return;
        }

        // Suggested price (SPRICE) of channels that have no analytics / pricing page of their own
        // (CJdropshipping, B2B). Everything typed in the Channels - Suggested Price modal is stored somewhere.
        Schema::create('channel_sprices', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191);
            $table->string('channel', 64); // compact lowercase key, e.g. cjdropshipping, b2b
            $table->decimal('sprice', 10, 2);
            $table->timestamps();

            $table->unique(['sku', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_sprices');
    }
};
