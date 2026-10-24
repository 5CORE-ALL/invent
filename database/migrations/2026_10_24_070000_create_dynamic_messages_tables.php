<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dynamic_message_rules')) {
            Schema::create('dynamic_message_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('event_key', 80);
                // Optional condition: <metric> <operator> <threshold>. NULL metric = always fire.
                $table->string('metric', 80)->nullable();
                $table->string('operator', 4)->nullable();
                $table->decimal('threshold', 14, 2)->nullable();
                $table->string('title', 190);
                $table->text('body');
                $table->json('recipient_user_ids');
                $table->string('frequency', 10)->default('daily'); // once | daily | weekly
                $table->boolean('allow_reply')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'event_key']);
            });
        }

        if (! Schema::hasTable('dynamic_messages')) {
            Schema::create('dynamic_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rule_id')->nullable();
                $table->unsignedBigInteger('recipient_user_id');
                $table->string('event_key', 80);
                $table->string('page_label', 80)->nullable();
                $table->string('title', 190);
                $table->text('body');
                $table->json('context')->nullable();
                $table->string('dedupe_key', 120);
                // Snapshot of the rule option at send time.
                $table->boolean('allow_reply')->default(false);
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamps();

                $table->unique(['rule_id', 'recipient_user_id', 'dedupe_key'], 'dyn_msg_unique_delivery');
                $table->index(['recipient_user_id', 'acknowledged_at']);
                $table->foreign('rule_id')->references('id')->on('dynamic_message_rules')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('dynamic_message_replies')) {
            Schema::create('dynamic_message_replies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('message_id');
                $table->unsignedBigInteger('user_id');
                $table->text('body');
                $table->timestamp('seen_at')->nullable();
                $table->timestamps();

                $table->unique('message_id');
                $table->foreign('message_id')->references('id')->on('dynamic_messages')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_message_replies');
        Schema::dropIfExists('dynamic_messages');
        Schema::dropIfExists('dynamic_message_rules');
    }
};
