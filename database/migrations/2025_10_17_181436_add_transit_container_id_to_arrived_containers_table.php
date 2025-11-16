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
        Schema::table('arrived_containers', function (Blueprint $table) {
            $table->unsignedBigInteger('transit_container_id')->nullable()->after('id'); // or after any other column
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('arrived_containers', function (Blueprint $table) {
            $table->dropColumn('transit_container_id');
        });
    }
};
