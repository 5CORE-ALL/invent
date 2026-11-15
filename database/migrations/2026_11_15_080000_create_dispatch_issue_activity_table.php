<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dispatch_issue_activity')) {
            return;
        }

        Schema::create('dispatch_issue_activity', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('issue_id')->nullable()->index();
            $table->string('group_id', 64)->nullable()->index();
            // created | updated | archived | restored | deleted
            $table->string('event', 20)->index();
            $table->string('action', 80)->nullable();
            $table->string('sku', 255)->nullable();
            $table->string('order_number', 255)->nullable();
            $table->string('department', 500)->nullable();
            // JSON: {"field": {"old": ..., "new": ...}, ...}
            $table->longText('changes')->nullable();
            // JSON copy of the full row — only stored for deletes.
            $table->longText('snapshot')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 255)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_issue_activity');
    }
};