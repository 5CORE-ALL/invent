<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promo fields live on each channel's *_data_view.value (Amazon JSON key format).
 * Drop the unused extra table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('channel_promo_pricing');
    }

    public function down(): void
    {
        if (Schema::hasTable('channel_promo_pricing')) {
            return;
        }

        Schema::create('channel_promo_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 64)->index();
            $table->string('sku', 191)->index();
            $table->decimal('prmt_pct', 8, 2)->nullable();
            $table->decimal('cpn_pct', 8, 2)->nullable();
            $table->decimal('dsc_pct', 8, 2)->nullable();
            $table->boolean('appr')->default(false);
            $table->string('push_prc_status', 64)->nullable();
            $table->decimal('push_prc_value', 12, 2)->nullable();
            $table->timestamp('push_prc_pushed_at')->nullable();
            $table->timestamps();
            $table->unique(['channel', 'sku']);
        });
    }
};
