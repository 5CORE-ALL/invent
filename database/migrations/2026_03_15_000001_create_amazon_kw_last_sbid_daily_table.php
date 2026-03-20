<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores KW Last SBID data only, date-wise daily (one row per campaign per day).
     */
    public function up(): void
    {
        Schema::create('amazon_kw_last_sbid_daily', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id');
            $table->string('profile_id');
            $table->date('report_date');
            $table->decimal('last_sbid', 10, 4)->nullable();
            $table->string('campaign_name')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'report_date']);
            $table->index(['campaign_id', 'report_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_kw_last_sbid_daily');
    }
};
