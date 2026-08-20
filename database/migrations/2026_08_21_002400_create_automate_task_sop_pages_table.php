<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('automate_task_sop_pages')) {
            return;
        }

        Schema::create('automate_task_sop_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('automate_task_id')->unique();
            $table->string('title', 255)->nullable();
            $table->longText('body')->nullable();
            $table->string('source_link', 2048)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automate_task_sop_pages');
    }
};
