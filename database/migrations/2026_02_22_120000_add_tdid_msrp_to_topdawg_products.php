<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topdawg_products', function (Blueprint $table) {
            $table->string('tdid')->nullable()->after('topdawg_listing_id');
            $table->decimal('msrp', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('topdawg_products', function (Blueprint $table) {
            $table->dropColumn(['tdid', 'msrp']);
        });
    }
};
