<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shelves')) {
            return;
        }

        Schema::create('shelves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rack_id')->constrained('racks')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->timestamps();

            $table->unique(['rack_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shelves');
    }
};
