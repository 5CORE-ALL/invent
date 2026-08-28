<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('amazon_datsheets')) {
            return;
        }
        if (Schema::hasColumn('amazon_datsheets', 'buy_box_percentage')) {
            return;
        }

        Schema::table('amazon_datsheets', function (Blueprint $table) {
            $table->decimal('buy_box_percentage', 8, 2)->nullable()->after('sessions_l30');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('amazon_datsheets') || ! Schema::hasColumn('amazon_datsheets', 'buy_box_percentage')) {
            return;
        }

        Schema::table('amazon_datsheets', function (Blueprint $table) {
            $table->dropColumn('buy_box_percentage');
        });
    }
};
