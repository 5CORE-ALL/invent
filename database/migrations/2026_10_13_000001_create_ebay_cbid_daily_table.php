<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ebay_cbid_daily')) {
            return;
        }

        Schema::create('ebay_cbid_daily', function (Blueprint $table) {
            $table->id();
            $table->string('account', 16);
            $table->string('listing_id', 32);
            $table->date('day');
            $table->decimal('bid_percentage', 8, 2)->nullable();
            $table->timestamps();
            $table->unique(['account', 'listing_id', 'day']);
            $table->index(['account', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay_cbid_daily');
    }
};
