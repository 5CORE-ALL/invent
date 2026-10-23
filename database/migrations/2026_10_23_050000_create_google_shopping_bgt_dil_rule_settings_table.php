<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_shopping_bgt_dil_rule_settings')) {
            return;
        }
        Schema::create('google_shopping_bgt_dil_rule_settings', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->json('rule');
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_shopping_bgt_dil_rule_settings');
    }
};
