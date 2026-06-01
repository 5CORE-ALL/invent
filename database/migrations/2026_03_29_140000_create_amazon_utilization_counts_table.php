<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amazon_utilization_counts')) {
            return;
        }

        Schema::create('amazon_utilization_counts', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id', 64)->index();
            $table->string('campaign_name', 512)->nullable();
            $table->enum('campaign_type', ['kw', 'hl', 'pt', 'fba_kw', 'fba_pt'])->index();
            $table->decimal('ub7', 10, 2)->nullable()->comment('7-day utilization % (spend vs budget*7)');
            $table->decimal('ub1', 10, 2)->nullable()->comment('1-day utilization % (spend vs budget)');
            $table->unsignedInteger('inventory')->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'campaign_type'], 'amazon_util_campaign_id_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_utilization_counts');
    }
};
