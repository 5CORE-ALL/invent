<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gofo_exceptions')) {
            return;
        }

        Schema::create('gofo_exceptions', function (Blueprint $table) {
            $table->id();
            $table->date('mail_date')->nullable();
            $table->string('tracking_number', 128)->nullable()->index();

            // Auto-fetched from the tracking number (also editable as a fallback).
            $table->string('order_id', 128)->nullable();
            $table->string('mm_slug', 64)->nullable();
            $table->string('channel_label', 96)->nullable();
            $table->string('sku', 191)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->string('product_title', 500)->nullable();
            $table->string('sku_image', 500)->nullable();
            $table->decimal('sku_price', 10, 2)->nullable()->comment('Shopify (our site) selling price of the SKU');
            $table->string('lookup_status', 16)->nullable()->comment('found | not_found');

            // Entered by hand.
            $table->string('resp_dept', 191)->nullable();
            $table->string('mail_response', 191)->nullable();
            $table->string('cc_action', 191)->nullable();
            $table->decimal('loss_amount', 10, 2)->nullable();
            $table->boolean('loss_manual')->default(false);
            $table->text('notes')->nullable();
            $table->json('attachments')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('mail_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gofo_exceptions');
    }
};
