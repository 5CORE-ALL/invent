<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipping_audit_logs')) {
            return;
        }

        Schema::create('shipping_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->boolean('all_messages_cleared')->default(false);
            $table->boolean('all_messages_replied_correctly')->default(false);
            $table->boolean('all_messages_noted_in_all_issues')->default(false);
            $table->boolean('all_followup_created_cleared_on_time')->default(false);
            $table->text('auditor_remarks')->nullable();
            $table->dateTime('audited_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['channel_id', 'audited_at'], 'shipping_audit_logs_channel_date_idx');

            if (Schema::hasTable('channel_master')) {
                $table->foreign('channel_id')
                    ->references('id')->on('channel_master')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_audit_logs');
    }
};
