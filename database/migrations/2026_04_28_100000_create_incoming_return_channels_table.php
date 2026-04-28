<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_return_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
            $table->string('channel', 255);
            $table->timestamps();
            $table->unique('inventory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_return_channels');
    }
};
