<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_health_metric_field_definitions')) {
            Schema::create('account_health_metric_field_definitions', function (Blueprint $table) {
                $table->id();
                $table->string('field_key', 64)->unique();
                $table->string('label', 255);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('account_health_channel_json_metrics')) {
            Schema::create('account_health_channel_json_metrics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id')->unique();
                $table->json('metrics');
                $table->timestamps();

                if (Schema::hasTable('channel_master')) {
                    $table->foreign('channel_id')->references('id')->on('channel_master')->cascadeOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_health_channel_json_metrics');
        Schema::dropIfExists('account_health_metric_field_definitions');
    }
};
