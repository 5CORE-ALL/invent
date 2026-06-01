<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders_on_hold_issues')) {
            return;
        }

        Schema::create('orders_on_hold_issues', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 128);
            $table->decimal('qty', 12, 2)->default(0);
            $table->string('parent')->nullable();
            $table->string('issue', 100);
            $table->string('created_by')->default('System');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->timestamps();

            $table->index('sku');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders_on_hold_issues');
    }
};
