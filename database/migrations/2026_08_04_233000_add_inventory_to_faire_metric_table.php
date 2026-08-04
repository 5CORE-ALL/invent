<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('faire_metric')) {
            return;
        }

        Schema::table('faire_metric', function (Blueprint $table) {
            if (! Schema::hasColumn('faire_metric', 'inventory')) {
                $table->unsignedInteger('inventory')->nullable()->after('price');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('faire_metric') || ! Schema::hasColumn('faire_metric', 'inventory')) {
            return;
        }

        Schema::table('faire_metric', function (Blueprint $table) {
            $table->dropColumn('inventory');
        });
    }
};
