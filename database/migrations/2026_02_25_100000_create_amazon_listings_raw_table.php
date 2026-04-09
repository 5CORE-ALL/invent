<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amazon_listings_raw')) {
            return;
        }

        Schema::create('amazon_listings_raw', function (Blueprint $table) {
            $table->id();
            $table->timestamp('report_imported_at')->nullable();
            $table->string('seller_sku', 100)->nullable()->index();
            $table->string('asin1', 20)->nullable()->index();
            $table->json('raw_data')->nullable()->comment('Full row from GET_MERCHANT_LISTINGS_ALL_DATA report');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_listings_raw');
    }
};
