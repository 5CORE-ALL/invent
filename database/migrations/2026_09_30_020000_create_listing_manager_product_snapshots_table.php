<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Listing Manager product modal: the combined Amazon / Main Store / drafts payload takes several
     * live API calls to build. Store the last built payload per SKU so the modal opens instantly and
     * only "Update from Store" (or the background refresh) hits the marketplaces again.
     */
    public function up(): void
    {
        if (Schema::hasTable('listing_manager_product_snapshots')) {
            return;
        }

        Schema::create('listing_manager_product_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->unique();
            $table->longText('payload')->nullable();
            $table->unsignedInteger('build_ms')->nullable();
            $table->timestamp('built_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('built_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_manager_product_snapshots');
    }
};
