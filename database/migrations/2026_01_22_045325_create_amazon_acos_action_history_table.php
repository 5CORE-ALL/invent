<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('amazon_acos_action_history', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id')->nullable();
            $table->string('sku')->nullable();
            $table->text('issue_found')->nullable();
            $table->text('action_taken')->nullable();
            $table->text('target_issues')->nullable(); // JSON string
            $table->string('campaign_type', 10)->default('KW'); // KW, PT, HL
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('campaign_id');
            $table->index('sku');
            $table->index('campaign_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_acos_action_history');
    }
};
