<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wayfair_price_uploads')) {
            return;
        }

        Schema::create('wayfair_price_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type', 16)->default('csv');
            $table->string('file_sha256', 64)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('upload_started_at')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('status', 32)->default('GENERATED')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('changed_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('wayfair_reference')->nullable();
            $table->longText('wayfair_response')->nullable();
            $table->longText('price_snapshot')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->string('created_by', 64)->nullable();
            $table->timestamps();

            $table->index('file_sha256');
            $table->index('generated_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wayfair_price_uploads');
    }
};
