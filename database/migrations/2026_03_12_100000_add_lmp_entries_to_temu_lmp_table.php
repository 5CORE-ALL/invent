<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temu_lmp', function (Blueprint $table) {
            $table->json('lmp_entries')->nullable()->after('lmp_link_2');
        });
    }

    public function down(): void
    {
        Schema::table('temu_lmp', function (Blueprint $table) {
            $table->dropColumn('lmp_entries');
        });
    }
};
