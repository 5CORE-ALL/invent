<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reverb_products', function (Blueprint $table) {
            $table->string('listing_state', 32)->nullable()->after('reverb_listing_id')->comment('Reverb state: draft, live, ended, sold');
        });
    }

    public function down(): void
    {
        Schema::table('reverb_products', function (Blueprint $table) {
            $table->dropColumn('listing_state');
        });
    }
};
