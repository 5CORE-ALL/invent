<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Latest raw GET /api/listings/{id}/bump response per Reverb listing
     * (shown as-is on /reverb-bump).
     */
    public function up(): void
    {
        if (Schema::hasTable('reverb_bump_raw')) {
            return;
        }

        Schema::create('reverb_bump_raw', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->nullable()->index();
            $table->string('reverb_listing_id', 64)->unique();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->longText('raw_response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reverb_bump_raw');
    }
};
