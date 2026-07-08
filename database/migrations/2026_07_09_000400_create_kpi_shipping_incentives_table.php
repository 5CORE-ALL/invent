<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kpi_shipping_incentives')) {
            return;
        }

        Schema::create('kpi_shipping_incentives', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 12, 2)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('updated_by', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_shipping_incentives');
    }
};
