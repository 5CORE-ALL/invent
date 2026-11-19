<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ebay2_variation_type_pushes') || Schema::hasColumn('ebay2_variation_type_pushes', 'live_variations')) {
            return;
        }

        Schema::table('ebay2_variation_type_pushes', function (Blueprint $table) {
            // JSON [{"sku": "AB 1", "specifics": {"Color": "Black"}}]: the child SKUs that exist on the live eBay 2
            // listing and the variation value each one has there (GetItem).
            $table->longText('live_variations')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('ebay2_variation_type_pushes') && Schema::hasColumn('ebay2_variation_type_pushes', 'live_variations')) {
            Schema::table('ebay2_variation_type_pushes', function (Blueprint $table) {
                $table->dropColumn('live_variations');
            });
        }
    }
};
