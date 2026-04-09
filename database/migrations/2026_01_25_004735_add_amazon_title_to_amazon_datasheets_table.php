<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('amazon_datsheets')) {
            return;
        }
        if (Schema::hasColumn('amazon_datsheets', 'amazon_title')) {
            return;
        }

        Schema::table('amazon_datsheets', function (Blueprint $table) {
            $table->string('amazon_title', 500)->nullable()->after('asin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('amazon_datsheets')) {
            return;
        }
        if (! Schema::hasColumn('amazon_datsheets', 'amazon_title')) {
            return;
        }

        Schema::table('amazon_datsheets', function (Blueprint $table) {
            $table->dropColumn('amazon_title');
        });
    }
};
