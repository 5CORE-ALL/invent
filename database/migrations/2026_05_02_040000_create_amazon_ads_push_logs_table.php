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
        Schema::create('amazon_ads_push_logs', function (Blueprint $table) {
            $table->id();
            $table->string('push_type', 50)->comment('sp_sbid, sb_sbid, sp_sbgt, sb_sbgt');
            $table->string('campaign_id', 100)->nullable();
            $table->string('campaign_name', 255)->nullable();
            $table->decimal('value', 10, 2)->nullable()->comment('Bid or Budget value');
            $table->enum('status', ['success', 'skipped', 'failed'])->default('skipped');
            $table->text('reason')->nullable()->comment('Reason for skip/failure');
            $table->json('request_data')->nullable()->comment('Full request data');
            $table->json('response_data')->nullable()->comment('API response data');
            $table->integer('http_status')->nullable();
            $table->string('source', 50)->default('web')->comment('web, command');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            
            // Indexes for faster queries
            $table->index('push_type');
            $table->index('campaign_id');
            $table->index('status');
            $table->index('created_at');
            $table->index(['push_type', 'status']);
            $table->index(['campaign_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_ads_push_logs');
    }
};
