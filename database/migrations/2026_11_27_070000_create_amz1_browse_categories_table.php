<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Browse category (the breadcrumb a shopper sees) of each Amz Data SKU. The page creates it too when first opened.
     */
    public function up(): void
    {
        if (! Schema::hasTable('amz1_browse_categories')) {
            Schema::create('amz1_browse_categories', function (Blueprint $table) {
                $table->id();
                $table->string('sku', 191)->unique();
                $table->string('asin', 20)->nullable()->index();
                $table->string('node_id', 40)->nullable();
                $table->string('path', 500)->nullable();
                $table->timestamp('fetched_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('amz1_browse_categories');
    }
};
