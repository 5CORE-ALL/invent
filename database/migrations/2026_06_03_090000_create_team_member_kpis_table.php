<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('team_member_kpis')) {
            return;
        }

        Schema::create('team_member_kpis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email')->nullable()->index();

            for ($k = 1; $k <= 5; $k++) {
                $table->string('kpi_' . $k . '_label')->nullable();
                $table->string('kpi_' . $k . '_value')->nullable();
            }

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_member_kpis');
    }
};
