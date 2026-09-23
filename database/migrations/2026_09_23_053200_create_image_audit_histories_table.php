<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('image_audit_histories')) {
            return;
        }

        Schema::create('image_audit_histories', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->boolean('fixed')->default(false);
            $table->text('details');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('sku');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_audit_histories');
    }
};
