<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_health_metric_value_histories')) {
            return;
        }

        Schema::create('account_health_metric_value_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->string('field_key', 64);
            $table->decimal('value', 12, 6)->nullable();
            $table->date('recorded_on');
            $table->timestamps();

            $table->index(['channel_id', 'field_key', 'recorded_on'], 'ahm_valhist_ch_fk_ro');
            $table->index(['channel_id', 'recorded_on'], 'ahm_valhist_ch_ro');

            if (Schema::hasTable('channel_master')) {
                $table->foreign('channel_id')->references('id')->on('channel_master')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_health_metric_value_histories');
    }
};
