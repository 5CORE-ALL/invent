<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders_on_hold_labels')) {
            return;
        }
        Schema::create('orders_on_hold_labels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('issue_id')->unique();
            $table->boolean('alternate_upgrade_done')->default(false);
            $table->boolean('stock_adjustment_done')->default(false);
            $table->boolean('refunded')->default(false);
            $table->boolean('label_voided')->default(false);
            $table->string('updated_by')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders_on_hold_labels');
    }
};
