<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amazon_acos_sbgt_rule_settings')) {
            return;
        }

        Schema::create('amazon_acos_sbgt_rule_settings', function (Blueprint $table) {
            $table->id();
            $table->json('rule');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_acos_sbgt_rule_settings');
    }
};
