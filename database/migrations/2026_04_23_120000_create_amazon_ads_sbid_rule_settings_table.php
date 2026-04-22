<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amazon_ads_sbid_rule_settings', function (Blueprint $table) {
            $table->id();
            $table->json('rule');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_ads_sbid_rule_settings');
    }
};
