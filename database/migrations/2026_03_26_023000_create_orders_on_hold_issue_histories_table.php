<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders_on_hold_issue_histories')) {
            return;
        }

        Schema::create('orders_on_hold_issue_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('orders_on_hold_issue_id')->nullable()->index();
            $table->string('event_type', 50)->default('created');
            $table->string('sku', 128);
            $table->decimal('qty', 12, 2)->default(0);
            $table->string('parent')->nullable();
            $table->string('marketplace_1')->nullable();
            $table->string('marketplace_2')->nullable();
            $table->string('issue', 100);
            $table->string('action_1')->nullable();
            $table->string('action_2')->nullable();
            $table->string('c_action_1')->nullable();
            $table->string('c_action_2')->nullable();
            $table->string('close_note')->nullable();
            $table->string('created_by')->default('System');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->timestamp('logged_at')->nullable()->index();
            $table->timestamps();

            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders_on_hold_issue_histories');
    }
};
