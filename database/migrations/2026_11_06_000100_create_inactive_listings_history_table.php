<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inactive_listings_history')) {
            return;
        }

        Schema::create('inactive_listings_history', function (Blueprint $table) {
            $table->id();
            // 'all' = the page total (Inactive Child SKUs badge), otherwise the channel slug.
            $table->string('channel_slug', 100);
            $table->string('channel_name', 191)->nullable();
            $table->date('snapshot_date');
            $table->unsignedInteger('parent_count')->default(0);
            $table->unsignedInteger('child_count')->default(0);
            $table->timestamps();

            $table->unique(['channel_slug', 'snapshot_date'], 'ilh_channel_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inactive_listings_history');
    }
};
