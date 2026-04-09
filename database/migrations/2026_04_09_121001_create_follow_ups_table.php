<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('follow_ups')) {
            return;
        }

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('assigned_user_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('follow_up_type', 32);
            $table->string('priority', 32);
            $table->string('status', 32);
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->dateTime('reminder_at')->nullable()->index();
            $table->dateTime('next_follow_up_at')->nullable()->index();
            $table->string('outcome', 32)->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['assigned_user_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
