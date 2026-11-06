<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amazon_today_ad_spend')) {
            return;
        }

        Schema::create('amazon_today_ad_spend', function (Blueprint $table) {
            $table->id();
            // Pacific calendar day the spend belongs to (Amazon Ads reports a US profile day in Pacific time).
            $table->date('spend_date')->unique();
            $table->decimal('sp_spend', 14, 2)->default(0);
            $table->decimal('sb_spend', 14, 2)->default(0);
            $table->decimal('total_spend', 14, 2)->default(0);
            $table->timestamp('pulled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_today_ad_spend');
    }
};
