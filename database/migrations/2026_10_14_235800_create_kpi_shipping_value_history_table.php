<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kpi_shipping_value_history')) {
            return;
        }

        Schema::create('kpi_shipping_value_history', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 191);
            $table->date('snapshot_date');
            $table->decimal('on_time_pct', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['channel', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_shipping_value_history');
    }
};
