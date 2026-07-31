<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amz_cvr_audit_histories')) {
            return;
        }

        Schema::create('amz_cvr_audit_histories', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 255)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 255)->nullable();
            $table->unsignedInteger('task_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amz_cvr_audit_histories');
    }
};
