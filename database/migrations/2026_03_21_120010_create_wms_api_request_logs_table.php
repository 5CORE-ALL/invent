<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wms_api_request_logs')) {
            return;
        }

        Schema::create('wms_api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 12);
            $table->string('path', 512);
            $table->unsignedSmallInteger('status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('request_body')->nullable();
            $table->text('response_body')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wms_api_request_logs');
    }
};
