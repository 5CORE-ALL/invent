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
        Schema::create('cvr_remarks', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->text('remark');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('is_solved')->default(false);
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
            // Index for faster queries
            $table->index(['sku', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cvr_remarks');
    }
};
