<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Description Master: full rich-HTML description (tables, inline images) pulled from a marketplace
     * and edited in the TinyMCE editor. Separate from the plain-text character tiers.
     */
    public function up(): void
    {
        if (Schema::hasTable('product_master') && ! Schema::hasColumn('product_master', 'description_html')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->longText('description_html')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_master') && Schema::hasColumn('product_master', 'description_html')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->dropColumn('description_html');
            });
        }
    }
};
