<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function createIssuesTable(string $table): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) {
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

    private function createHistoryTable(string $table): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) {
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

    public function up(): void
    {
        $this->createIssuesTable('carrier_issue_issues');
        $this->createHistoryTable('carrier_issue_issue_histories');

        $this->createIssuesTable('label_issue_issues');
        $this->createHistoryTable('label_issue_issue_histories');

        $this->createIssuesTable('dispatch_issue_issues');
        $this->createHistoryTable('dispatch_issue_issue_histories');
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_issue_issue_histories');
        Schema::dropIfExists('dispatch_issue_issues');
        Schema::dropIfExists('label_issue_issue_histories');
        Schema::dropIfExists('label_issue_issues');
        Schema::dropIfExists('carrier_issue_issue_histories');
        Schema::dropIfExists('carrier_issue_issues');
    }
};
