<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('image_master_activity_logs')) {
            return;
        }

        Schema::create('image_master_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 255)->index();
            // add | edit | remove | reorder | upload | delete | shopify_pull | ...
            $table->string('action', 32)->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 255)->nullable();
            $table->string('user_email', 255)->nullable();
            $table->string('summary', 500)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_master_activity_logs');
    }
};
