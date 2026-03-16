<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('refund_rate', function (Blueprint $table) {
            $table->id();
            $table->integer('channel_id')->nullable();
            $table->string('account_health_links')->nullable();
            $table->string('allowed')->nullable();
            $table->string('current')->nullable();
            $table->date('report_date')->nullable();
            $table->string('prev_1')->nullable();
            $table->date('prev_1_date')->nullable();
            $table->string('prev_2')->nullable();
            $table->date('prev_2_date')->nullable();
            $table->string('what')->nullable();
            $table->string('why')->nullable();
            $table->string('action')->nullable();
            $table->string('c_action')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_rate');
    }
};
