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
       Schema::create('channel_settings', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->decimal('factor',5,2)->default(1.00);
        $table->boolean('enabled')->default(true);
        $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_settings');
    }
};
