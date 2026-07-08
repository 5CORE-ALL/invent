<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amazon_ads_audit_histories', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id')->nullable();
            $table->string('campaign_name')->nullable();
            $table->boolean('fixed')->default(false);   // answer to "Fixed?"
            $table->text('details');                     // free-text detail (mandatory)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('campaign_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_ads_audit_histories');
    }
};
