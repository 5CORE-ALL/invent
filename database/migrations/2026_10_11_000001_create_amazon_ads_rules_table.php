<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One table for Amazon ads budget rules. Same shape as ebay_sbid_rules:
 * unique key + JSON rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amazon_ads_rules')) {
            return;
        }
        Schema::create('amazon_ads_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('rule');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_ads_rules');
    }
};
