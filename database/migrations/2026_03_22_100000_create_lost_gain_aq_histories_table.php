<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lost_gain_aq_histories')) {
            return;
        }

        Schema::create('lost_gain_aq_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_uuid')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('inventory_id')->index();
            $table->string('sku', 191);
            $table->integer('old_to_adjust')->nullable();
            $table->integer('new_to_adjust')->nullable();
            $table->decimal('old_loss_gain', 12, 2)->nullable();
            $table->decimal('new_loss_gain', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_gain_aq_histories');
    }
};
