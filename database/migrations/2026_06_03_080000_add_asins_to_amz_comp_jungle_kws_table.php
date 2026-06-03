<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('amz_comp_jungle_kws')) {
            return;
        }

        if (! Schema::hasColumn('amz_comp_jungle_kws', 'asins')) {
            Schema::table('amz_comp_jungle_kws', function (Blueprint $table) {
                $table->json('asins')->nullable()->after('search_kw');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('amz_comp_jungle_kws', 'asins')) {
            Schema::table('amz_comp_jungle_kws', function (Blueprint $table) {
                $table->dropColumn('asins');
            });
        }
    }
};
