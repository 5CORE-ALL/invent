<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lqs_amz_history', function (Blueprint $table) {
            $table->decimal('avg_rating', 4, 2)->default(0)->after('avg_lqs');
        });
    }

    public function down(): void
    {
        Schema::table('lqs_amz_history', function (Blueprint $table) {
            $table->dropColumn('avg_rating');
        });
    }
};
