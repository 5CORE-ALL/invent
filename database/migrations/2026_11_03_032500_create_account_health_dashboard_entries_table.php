<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_health_dashboard_entries')) {
            return;
        }

        Schema::create('account_health_dashboard_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->index();
            $table->string('channel', 191);
            $table->string('metric', 64);
            $table->string('value_column', 16);
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 191)->nullable();
            $table->date('entered_on')->index();
            $table->timestamps();
            $table->index(['channel_id', 'metric', 'entered_on'], 'ah_dash_entries_ch_metric_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_health_dashboard_entries');
    }
};
