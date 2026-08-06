<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_vs_sheet_settings')) {
            return;
        }

        Schema::create('api_vs_sheet_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->unique();
            $table->string('download_source', 50)->nullable();
            $table->string('upload_source', 50)->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('channel_id')
                ->references('id')
                ->on('channel_master')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_vs_sheet_settings');
    }
};
