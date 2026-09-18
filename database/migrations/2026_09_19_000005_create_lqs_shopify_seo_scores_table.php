<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lqs_shopify_seo_scores')) {
            return;
        }

        Schema::create('lqs_shopify_seo_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_product_id')->unique();
            $table->string('handle')->nullable();
            $table->string('title')->nullable();
            $table->string('seo_title', 500)->nullable();
            $table->text('seo_description')->nullable();
            $table->string('focus_keyphrase', 255)->nullable();
            $table->json('skus')->nullable();
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->string('seo_rating', 16)->default('na');
            $table->unsignedTinyInteger('readability_score')->nullable();
            $table->string('readability_rating', 16)->default('na');
            $table->json('findings')->nullable();
            $table->json('yoast_payload')->nullable();
            $table->string('source', 32)->default('catalog');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index('seo_rating');
            $table->index('readability_rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lqs_shopify_seo_scores');
    }
};
