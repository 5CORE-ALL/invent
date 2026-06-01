<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('communication_logs')) {
            return;
        }

        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('follow_up_id')->nullable()->constrained('follow_ups')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 32);
            $table->text('message');
            $table->string('attachment_path', 2048)->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index('follow_up_id');
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
    }
};
