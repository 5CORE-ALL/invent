<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('image_master_push_histories')) {
            return;
        }

        Schema::create('image_master_push_histories', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 255)->index();
            $table->string('job_id', 64)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 255)->nullable();
            $table->string('user_email', 255)->nullable();
            $table->string('marketplace', 64);
            $table->boolean('success')->default(false);
            $table->string('mode', 16)->nullable();
            $table->unsignedSmallInteger('image_count')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('pushed_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_master_push_histories');
    }
};
