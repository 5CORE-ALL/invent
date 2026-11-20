<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spare_part_packagings') && ! Schema::hasColumn('spare_part_packagings', 'qty_available')) {
            Schema::table('spare_part_packagings', function (Blueprint $table) {
                $table->integer('qty_available')->nullable()->after('msl');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('spare_part_packagings') && Schema::hasColumn('spare_part_packagings', 'qty_available')) {
            Schema::table('spare_part_packagings', function (Blueprint $table) {
                $table->dropColumn('qty_available');
            });
        }
    }
};
