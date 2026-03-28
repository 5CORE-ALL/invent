<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('qc_and_packing_issues')) {
            Schema::create('qc_and_packing_issues', function (Blueprint $table) {
                $table->id();
                $table->string('sku', 128);
                $table->double('qty')->default(0);
                $table->double('order_qty')->nullable();
                $table->string('parent')->nullable();
                $table->string('marketplace_1')->nullable();
                $table->string('marketplace_2')->nullable();
                $table->string('what_happened', 50)->nullable();
                $table->string('issue')->nullable();
                $table->string('issue_remark')->nullable();
                $table->string('action_1')->nullable();
                $table->string('action_1_remark')->nullable();
                $table->string('replacement_tracking', 50)->nullable();
                $table->string('c_action_1')->nullable();
                $table->string('c_action_1_remark')->nullable();
                $table->string('close_note')->nullable();
                $table->string('created_by')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->boolean('is_archived')->default(false)->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->string('archived_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('qc_and_packing_issue_histories')) {
            Schema::create('qc_and_packing_issue_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('orders_on_hold_issue_id')->nullable()->index();
                $table->string('event_type')->nullable();
                $table->integer('revision_no')->default(0)->nullable();
                $table->string('sku', 128)->nullable();
                $table->double('qty')->default(0);
                $table->double('order_qty')->nullable();
                $table->string('parent')->nullable();
                $table->string('marketplace_1')->nullable();
                $table->string('marketplace_2')->nullable();
                $table->string('what_happened', 50)->nullable();
                $table->string('issue')->nullable();
                $table->string('issue_remark')->nullable();
                $table->string('action_1')->nullable();
                $table->string('action_1_remark')->nullable();
                $table->string('replacement_tracking', 50)->nullable();
                $table->string('c_action_1')->nullable();
                $table->string('c_action_1_remark')->nullable();
                $table->string('close_note')->nullable();
                $table->string('created_by')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamp('logged_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_and_packing_issue_histories');
        Schema::dropIfExists('qc_and_packing_issues');
    }
};
