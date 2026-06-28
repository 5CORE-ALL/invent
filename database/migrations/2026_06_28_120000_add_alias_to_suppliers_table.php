<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('suppliers') && !Schema::hasColumn('suppliers', 'alias')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('alias')->nullable()->after('company');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('suppliers') && Schema::hasColumn('suppliers', 'alias')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('alias');
            });
        }
    }
};
