<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aliexpress_metric') || Schema::hasColumn('aliexpress_metric', 'listing_status')) {
            return;
        }

        Schema::table('aliexpress_metric', function (Blueprint $table) {
            $table->string('listing_status', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('aliexpress_metric') && Schema::hasColumn('aliexpress_metric', 'listing_status')) {
            Schema::table('aliexpress_metric', function (Blueprint $table) {
                $table->dropColumn('listing_status');
            });
        }
    }
};
