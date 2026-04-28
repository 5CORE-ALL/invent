<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_shopping_campaigns_raw_rule_settings', function (Blueprint $table) {
            $table->id();
            $table->json('rule');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_shopping_campaigns_raw_rule_settings');
    }
};
