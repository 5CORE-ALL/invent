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
        Schema::create('meta_all_ads', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_name', 191)->unique();
            $table->string('campaign_id', 191)->nullable();
            $table->enum('campaign_delivery', ['active', 'inactive', 'not_delivering'])->default('inactive');
            $table->decimal('bgt', 15, 2)->nullable();
            $table->bigInteger('imp_l30')->default(0);
            $table->decimal('spent_l30', 15, 2)->default(0);
            $table->integer('clicks_l30')->default(0);
            $table->timestamps();
            
            $table->index('campaign_delivery');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_all_ads');
    }
};
