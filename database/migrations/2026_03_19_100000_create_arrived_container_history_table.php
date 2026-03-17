<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arrived_container_history', function (Blueprint $table) {
            $table->id();
            $table->string('action_type', 50);
            $table->unsignedBigInteger('arrived_container_id')->nullable();
            $table->string('from_tab')->nullable();
            $table->string('to_tab')->nullable();
            $table->string('our_sku')->nullable();
            $table->text('details')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['action_type', 'created_at']);
            $table->index(['our_sku', 'created_at']);
            $table->index(['to_tab', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arrived_container_history');
    }
};
