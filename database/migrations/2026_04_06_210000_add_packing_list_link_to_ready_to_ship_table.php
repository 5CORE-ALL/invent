<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ready_to_ship', function (Blueprint $table) {
            if (! Schema::hasColumn('ready_to_ship', 'packing_list_link')) {
                $table->string('packing_list_link', 2048)->nullable()->after('packing_list');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ready_to_ship', function (Blueprint $table) {
            if (Schema::hasColumn('ready_to_ship', 'packing_list_link')) {
                $table->dropColumn('packing_list_link');
            }
        });
    }
};
