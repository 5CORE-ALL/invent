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
        Schema::table('reverb_products', function (Blueprint $table) {
            $table->string('bump_bid', 10)->nullable()->after('remaining_inventory')->comment('Bump bid % from Reverb API e.g. 2%, 5%');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reverb_products', function (Blueprint $table) {
            $table->dropColumn('bump_bid');
        });
    }
};
