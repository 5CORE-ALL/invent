<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amazon_listings_raw', function (Blueprint $table) {
            $table->id();
            $table->timestamp('report_imported_at')->nullable();
            $table->string('seller_sku')->nullable()->index();
            $table->string('asin1')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->string('thumbnail_image')->nullable();
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_listings_raw');
    }
};
