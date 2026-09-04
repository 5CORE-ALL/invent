<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('advertisement_master_custom_rows')) {
            return;
        }

        Schema::create('advertisement_master_custom_rows', function (Blueprint $table) {
            $table->id();
            $table->string('channel_name', 80);
            $table->string('type_name', 191);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisement_master_custom_rows');
    }
};
