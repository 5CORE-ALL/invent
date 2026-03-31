<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reverb_products') || Schema::hasColumn('reverb_products', 'features')) {
            return;
        }

        Schema::table('reverb_products', function (Blueprint $table) {
            $table->longText('features')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reverb_products') || ! Schema::hasColumn('reverb_products', 'features')) {
            return;
        }

        Schema::table('reverb_products', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }
};
