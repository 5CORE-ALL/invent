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
        Schema::create('rfq_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');       
            $table->string('title');      
            $table->string('slug')->unique(); 
            $table->string('main_image')->nullable();
            $table->text('subtitle')->nullable();     
            $table->json('fields');      
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfq_forms');
    }
};
