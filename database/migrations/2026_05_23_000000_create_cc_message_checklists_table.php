<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cc_message_checklists')) {
            Schema::create('cc_message_checklists', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id')->index();
                $table->string('channel', 191)->nullable()->index();   // denormalized for reporting
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name', 191)->nullable();          // denormalized snapshot
                $table->boolean('messages_resolved')->default(false);
                $table->boolean('returns_resolved')->default(false);
                $table->boolean('unresolved_messages_followup')->default(false);
                $table->boolean('activity_documented')->default(false);
                $table->text('notes')->nullable();
                $table->timestamp('submitted_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cc_message_checklists');
    }
};
