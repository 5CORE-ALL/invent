<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('driver_folders')) {
            Schema::create('driver_folders', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('parent_id')->default(0)->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('driver_data')) {
            Schema::create('driver_data', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('title')->nullable();
                $table->string('location_url')->nullable();
                $table->unsignedBigInteger('folder_id')->default(0)->index();
                $table->string('file_name')->nullable();
                $table->string('file_data')->nullable();
                $table->string('file_size')->nullable();
                $table->string('file_extension')->nullable();
                $table->string('file_type')->default('type');
                $table->timestamps();
                $table->unsignedBigInteger('created_by')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_data');
        Schema::dropIfExists('driver_folders');
    }
};
