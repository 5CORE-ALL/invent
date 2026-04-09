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

        if (Schema::hasColumn('amazon_datsheets', 'price_lmpa')) {
            return;
        }

        $afterPrice = Schema::hasColumn('amazon_datsheets', 'price');

        Schema::table('amazon_datsheets', function (Blueprint $table) use ($afterPrice): void {
            if ($afterPrice) {
                $table->decimal('price_lmpa', 10, 2)->nullable()->after('price');
            } else {
                $table->decimal('price_lmpa', 10, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('amazon_datsheets') || ! Schema::hasColumn('amazon_datsheets', 'price_lmpa')) {
            return;
        }

        Schema::table('amazon_datsheets', function (Blueprint $table) {
            $table->dropColumn('price_lmpa');
        });
    }
};
