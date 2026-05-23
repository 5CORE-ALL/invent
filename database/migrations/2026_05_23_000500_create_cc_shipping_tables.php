<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cc_shipping_checklists')) {
            Schema::create('cc_shipping_checklists', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id')->index();
                $table->string('channel', 191)->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name', 191)->nullable();
                $table->boolean('messages_resolved')->default(false);
                $table->boolean('unresolved_messages_followup')->default(false);
                $table->boolean('activity_documented')->default(false);
                $table->text('notes')->nullable();
                $table->timestamp('submitted_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cc_shipping_returns_checklists')) {
            Schema::create('cc_shipping_returns_checklists', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id')->index();
                $table->string('channel', 191)->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name', 191)->nullable();
                $table->boolean('messages_resolved')->default(false);
                $table->boolean('unresolved_messages_followup')->default(false);
                $table->boolean('activity_documented')->default(false);
                $table->text('notes')->nullable();
                $table->timestamp('submitted_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cc_shipping_channel_next')) {
            Schema::create('cc_shipping_channel_next', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id')->unique();
                $table->unsignedTinyInteger('next_value')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->string('updated_by_name', 191)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cc_shipping_returns_channel_next')) {
            Schema::create('cc_shipping_returns_channel_next', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id')->unique();
                $table->unsignedTinyInteger('next_value')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->string('updated_by_name', 191)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cc_shipping_returns_channel_next');
        Schema::dropIfExists('cc_shipping_channel_next');
        Schema::dropIfExists('cc_shipping_returns_checklists');
        Schema::dropIfExists('cc_shipping_checklists');
    }
};
