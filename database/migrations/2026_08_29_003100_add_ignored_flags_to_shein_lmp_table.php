<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shein_lmp') || Schema::hasColumn('shein_lmp', 'ignored_1')) {
            return;
        }
        Schema::table('shein_lmp', function (Blueprint $table) {
            $table->boolean('ignored_1')->default(false)->after('url_1');
            $table->boolean('ignored_2')->default(false)->after('url_2');
            $table->boolean('ignored_3')->default(false)->after('url_3');
            $table->boolean('ignored_4')->default(false)->after('url_4');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shein_lmp') || ! Schema::hasColumn('shein_lmp', 'ignored_1')) {
            return;
        }
        Schema::table('shein_lmp', function (Blueprint $table) {
            $table->dropColumn(['ignored_1', 'ignored_2', 'ignored_3', 'ignored_4']);
        });
    }
};
