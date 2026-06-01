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
        Schema::create('compliance_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->nullable();
            $table->integer('inv')->nullable();
            $table->text('fcc')->nullable();
            $table->text('gcc')->nullable();
            $table->text('ul')->nullable();
            $table->text('battery')->nullable();
            $table->json('certificate_available')->nullable();
            $table->text('certificate_files')->nullable();
            $table->string('status')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_certificates');
    }
};
