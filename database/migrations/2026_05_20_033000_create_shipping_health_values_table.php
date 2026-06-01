<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipping_health_values')) {
            return;
        }

        Schema::create('shipping_health_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->decimal('value', 10, 2);
            $table->date('recorded_on');
            $table->dateTime('recorded_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['channel_id', 'recorded_on'], 'shipping_health_values_channel_date_idx');

            if (Schema::hasTable('channel_master')) {
                $table->foreign('channel_id')
                    ->references('id')->on('channel_master')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_health_values');
    }
};
