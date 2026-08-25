<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amazon_zero_view_diagnostics')) {
            return;
        }

        Schema::create('amazon_zero_view_diagnostics', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->unique();
            $table->string('asin', 32)->nullable()->index();
            $table->string('marketplace', 64)->nullable();
            $table->string('account', 64)->nullable();
            $table->string('product_name', 512)->nullable();
            $table->decimal('inventory', 12, 2)->nullable();
            $table->string('listing_status', 32)->nullable();
            $table->string('suppression_status', 32)->nullable();
            $table->string('buyable_status', 32)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('featured_offer_status', 64)->nullable();
            $table->unsignedInteger('l7_views')->nullable();
            $table->unsignedInteger('l30_views')->nullable()->index();
            $table->unsignedInteger('l7_sessions')->nullable();
            $table->unsignedInteger('l30_sessions')->nullable();
            $table->string('search_index_status', 64)->nullable();
            $table->string('category_status', 64)->nullable();
            $table->string('browse_node_status', 64)->nullable();
            $table->string('main_image_status', 32)->nullable();
            $table->string('title_status', 32)->nullable();
            $table->string('diagnostic_status', 64)->nullable()->index();
            $table->text('problem')->nullable();
            $table->text('recommended_action')->nullable();
            $table->json('diagnostic_data')->nullable();
            $table->string('run_status', 32)->nullable()->index();
            $table->text('api_errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['account', 'marketplace'], 'amz_zvd_account_marketplace_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_zero_view_diagnostics');
    }
};
