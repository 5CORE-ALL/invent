<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('std_pricing_sprc_dil', function (Blueprint $table) {
            $table->json('formula')->nullable()->after('lmp_rules');
        });
    }

    public function down(): void
    {
        Schema::table('std_pricing_sprc_dil', function (Blueprint $table) {
            $table->dropColumn('formula');
        });
    }
};
