<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qc_masters_entries')) {
            return;
        }

        Schema::create('qc_masters_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_master_id')->unique()->constrained('product_master')->cascadeOnDelete();
            $table->text('problem_issue')->nullable();
            $table->text('suggestion_improve')->nullable();
            $table->string('image_path', 500)->nullable();
            $table->unsignedInteger('image_size_kb')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_masters_entries');
    }
};
