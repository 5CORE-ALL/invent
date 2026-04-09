<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shopify_customers')) {
            return;
        }

        Schema::create('shopify_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_customer_id')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('sync_status', 32)->index();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamps();

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_customers');
    }
};
