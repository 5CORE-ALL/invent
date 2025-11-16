<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('zero_visibility_daily_totals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date')->index();
            $table->integer('total')->default(0);
            $table->timestamps();
            $table->unique(['date'], 'zero_visibility_date_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('zero_visibility_daily_totals');
    }
};
