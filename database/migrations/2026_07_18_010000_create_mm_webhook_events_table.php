<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mm_webhook_events')) {
            return;
        }

        Schema::create('mm_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32)->default('shopify')->index();
            $table->string('webhook_id', 128)->nullable();
            $table->string('topic', 128)->nullable()->index();
            $table->string('inventory_item_id', 64)->nullable()->index();
            $table->json('payload');
            $table->string('status', 32)->default('received')->index();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'webhook_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_webhook_events');
    }
};
