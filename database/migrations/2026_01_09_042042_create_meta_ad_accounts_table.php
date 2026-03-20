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
        Schema::create('meta_ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('meta_id', 191)->unique();
            $table->string('account_id', 191)->nullable();
            $table->string('name')->nullable();
            $table->string('account_status', 50)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('timezone_name', 100)->nullable();
            $table->timestamp('meta_updated_time')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'meta_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_ad_accounts');
    }
};
