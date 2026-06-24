<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds an `archived_at` timestamp so rows can be soft-hidden from the
     * On Sea Transit board without losing history.  We deliberately use a
     * plain nullable timestamp (not Laravel's SoftDeletes column) because
     * the rest of this controller talks to the table with raw queries
     * and we don't want every query to suddenly grow a global scope.
     */
    public function up(): void
    {
        Schema::table('on_sea_transit', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('details');
        });
    }

    public function down(): void
    {
        Schema::table('on_sea_transit', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
