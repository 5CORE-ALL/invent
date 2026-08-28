<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sof_shipment_status_overrides')) {
            return;
        }

        Schema::create('sof_shipment_status_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('mm_slug', 64);
            $table->string('order_key', 191);
            $table->string('order_id', 128)->nullable();
            $table->string('shipment_status', 64);
            $table->string('shipment_status_detail', 500)->nullable();
            $table->timestamps();

            $table->unique(['mm_slug', 'order_key'], 'sof_ship_override_slug_key_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sof_shipment_status_overrides');
    }
};
