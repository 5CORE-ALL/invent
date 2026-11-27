<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_reviews')) {
            return;
        }

        Schema::create('marketplace_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->index();
            // Marketplace slug from MarketplaceManagerRegistry (amazon, walmart, ebay1, ...).
            $table->string('marketplace', 40)->index();
            $table->string('external_product_id', 120)->nullable();
            $table->string('external_review_id', 191)->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('title', 255)->nullable();
            $table->text('body')->nullable();
            $table->string('reviewer_name', 191)->nullable();
            $table->boolean('verified')->default(false);
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_url', 500)->nullable();
            $table->json('images')->nullable();
            // serpapi | csv | manual
            $table->string('source', 20)->default('manual');
            // Same review fetched twice must not create a second row.
            $table->char('fingerprint', 40)->unique();

            // Shopify push (through the review app, Judge.me).
            $table->string('shopify_product_id', 40)->nullable();
            $table->string('shopify_status', 12)->default('pending')->index(); // pending|pushed|failed|skipped
            $table->dateTime('shopify_pushed_at')->nullable();
            $table->string('shopify_error', 500)->nullable();
            $table->unsignedSmallInteger('shopify_attempts')->default(0);

            $table->timestamps();

            $table->index(['marketplace', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_reviews');
    }
};
