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
        Schema::create('meta_action_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('action_type', 50)->index(); // pause, resume, update_budget, bulk_update, etc.
            $table->string('entity_type', 50)->index(); // campaign, adset, ad
            $table->string('entity_meta_id', 191)->index();
            $table->string('status', 50)->index(); // success, failed
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->string('meta_error_code', 50)->nullable();
            $table->text('meta_error_message')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['entity_type', 'entity_meta_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_action_logs');
    }
};
