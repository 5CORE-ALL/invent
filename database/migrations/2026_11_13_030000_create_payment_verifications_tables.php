<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_verifications')) {
            Schema::create('payment_verifications', function (Blueprint $table) {
                $table->id();
                // Normalised channel name (lowercase letters/digits only), e.g. "ebay3".
                $table->string('channel_key', 191)->unique();
                $table->string('channel', 191);
                // Payment received. Up to six characters, kept as typed.
                $table->string('pmt_recd', 6)->nullable();
                $table->string('bank_account', 255)->nullable();
                $table->string('link', 1000)->nullable();
                $table->string('updated_by', 191)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_verification_histories')) {
            Schema::create('payment_verification_histories', function (Blueprint $table) {
                $table->id();
                $table->string('channel_key', 191)->index();
                $table->string('channel', 191);
                $table->string('amount', 6);
                $table->string('recorded_by', 191)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_verification_histories');
        Schema::dropIfExists('payment_verifications');
    }
};
