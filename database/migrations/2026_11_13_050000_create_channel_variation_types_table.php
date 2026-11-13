<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_variation_types')) {
            return;
        }

        Schema::create('channel_variation_types', function (Blueprint $table) {
            $table->id();
            // Normalised channel name (lowercase letters/digits only), e.g. "ebay3".
            $table->string('channel_key', 191)->unique();
            $table->string('channel', 191);
            // 'Variation' or 'Single'.
            $table->string('variation_type', 10);
            $table->string('updated_by', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_variation_types');
    }
};
