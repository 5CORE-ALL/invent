<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('walmart_campaign_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_range');
            $table->string('campaign_id');
            $table->string('campaignName');
            $table->decimal('budget', 10, 2)->default(0);
            $table->decimal('spend', 10, 2)->default(0);
            $table->decimal('cpc', 10, 2)->default(0);
            $table->bigInteger('impression')->default(0);
            $table->bigInteger('clicks')->default(0);
            $table->bigInteger('sold')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('walmart_campaign_reports');
    }
};
