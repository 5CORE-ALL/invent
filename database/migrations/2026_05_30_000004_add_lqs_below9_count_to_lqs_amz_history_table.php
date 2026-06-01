<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lqs_amz_history', function (Blueprint $table) {
            $table->unsignedInteger('lqs_below_9_count')->default(0)->after('avg_rating');
        });
    }

    public function down(): void
    {
        Schema::table('lqs_amz_history', function (Blueprint $table) {
            $table->dropColumn('lqs_below_9_count');
        });
    }
};
