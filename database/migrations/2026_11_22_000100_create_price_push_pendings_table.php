<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('price_push_pendings')) {
            return;
        }

        Schema::create('price_push_pendings', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191);
            $table->string('marketplace', 64);          // compact lowercase key sent to /cvr-master-push-price
            $table->string('channel', 120)->nullable(); // label shown to the user
            $table->decimal('price', 10, 2);
            $table->json('extras')->nullable();         // self_pick_price / goods_id / sku_id / product_id for the retry
            $table->string('status', 16)->default('pending'); // pending | pushed | failed (gave up retrying)
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->string('requested_by', 120)->nullable();
            $table->timestamps();

            $table->unique(['sku', 'marketplace']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_push_pendings');
    }
};
