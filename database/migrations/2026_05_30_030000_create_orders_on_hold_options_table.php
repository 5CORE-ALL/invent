<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders_on_hold_options')) {
            return;
        }
        Schema::create('orders_on_hold_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('issue_id')->unique();
            $table->string('variant_sku')->nullable();
            $table->string('upgrade_sku')->nullable();
            $table->string('updated_by')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders_on_hold_options');
    }
};
