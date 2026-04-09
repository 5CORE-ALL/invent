<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sku_reviews')) {
            return;
        }

        Schema::create('sku_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('marketplace', 50)->index();
            $table->string('review_id', 255)->nullable();
            $table->tinyInteger('rating')->nullable();
            $table->string('review_title', 500)->nullable();
            $table->longText('review_text')->nullable();
            $table->string('reviewer_name', 255)->nullable();
            $table->date('review_date')->nullable()->index();
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable()->index();
            $table->enum('issue_category', [
                'quality', 'packaging', 'shipping', 'service',
                'wrong_item', 'missing_parts', 'other'
            ])->nullable()->index();
            $table->text('ai_summary')->nullable();
            $table->text('ai_reply')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->string('department', 100)->nullable();
            $table->enum('source_type', ['api', 'csv'])->default('api');
            $table->boolean('is_flagged')->default(false)->index();
            $table->timestamps();

            $table->unique(['marketplace', 'review_id'], 'unique_marketplace_review');
            $table->index(['sku', 'marketplace']);
            $table->index(['supplier_id', 'sentiment']);
            $table->index(['review_date', 'sentiment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_reviews');
    }
};
