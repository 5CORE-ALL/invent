<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds a per-container `freight` amount so the On Sea Transit screen
     * can show a Freight column (between Value and Due) and a matching
     * total on the header strip.  Stored as decimal(15,2) to match the
     * existing money columns on the same table.
     */
    public function up(): void
    {
        Schema::table('on_sea_transit', function (Blueprint $table) {
            $table->decimal('freight', 15, 2)->nullable()->after('invoice_value');
        });
    }

    public function down(): void
    {
        Schema::table('on_sea_transit', function (Blueprint $table) {
            $table->dropColumn('freight');
        });
    }
};
