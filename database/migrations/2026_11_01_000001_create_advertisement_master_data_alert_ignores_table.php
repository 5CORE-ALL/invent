<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('advertisement_master_data_alert_ignores')) {
            return;
        }

        Schema::create('advertisement_master_data_alert_ignores', function (Blueprint $table) {
            $table->id();
            $table->string('channel_key', 191);
            $table->string('field', 64);
            $table->timestamps();

            $table->unique(['channel_key', 'field'], 'adm_data_alert_ignores_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisement_master_data_alert_ignores');
    }
};
