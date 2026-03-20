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
        Schema::table('reverb_view_data', function (Blueprint $table) {
            $table->string('recommended_bid', 50)->nullable()->after('values')->comment('Recommended bid e.g. 5%');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reverb_view_data', function (Blueprint $table) {
            $table->dropColumn('recommended_bid');
        });
    }
};
