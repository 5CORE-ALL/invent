<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('reverb_view_data', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->string('parent')->nullable();
            $table->json('values')->nullable(); // To store bump, S bump, s price, etc.
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('reverb_view_data');
    }
};
