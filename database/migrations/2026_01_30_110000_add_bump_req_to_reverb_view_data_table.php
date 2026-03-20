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
            $table->string('bump_req', 10)->nullable()->after('recommended_bid')->comment('Bump Req like NRA: REQ or NR');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reverb_view_data', function (Blueprint $table) {
            $table->dropColumn('bump_req');
        });
    }
};
