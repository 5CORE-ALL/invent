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
        Schema::create('amazon_bid_caps', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique()->index();
            $table->decimal('bid_cap', 10, 2)->nullable()->comment('Maximum bid cap for this SKU');
            $table->unsignedBigInteger('user_id')->nullable()->comment('User who last updated');
            $table->timestamp('last_updated_at')->nullable()->comment('When bid cap was last updated');
            $table->timestamps();
            
            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_bid_caps');
    }
};
