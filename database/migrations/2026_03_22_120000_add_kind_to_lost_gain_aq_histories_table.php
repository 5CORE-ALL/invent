<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lost_gain_aq_histories', function (Blueprint $table) {
            $table->string('kind', 8)->default('aq')->after('batch_uuid')->index();
        });
    }

    public function down(): void
    {
        Schema::table('lost_gain_aq_histories', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
