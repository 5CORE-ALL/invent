<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bins')) {
            return;
        }

        Schema::create('bins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shelf_id')->constrained('shelves')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->unsignedInteger('capacity')->nullable();
            $table->string('full_location_code', 191)->nullable()->index();
            $table->timestamps();

            $table->unique(['shelf_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bins');
    }
};
