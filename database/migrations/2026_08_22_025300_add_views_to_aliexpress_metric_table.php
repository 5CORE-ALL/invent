<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aliexpress_metric') || Schema::hasColumn('aliexpress_metric', 'views')) {
            return;
        }

        Schema::table('aliexpress_metric', function (Blueprint $table) {
            $table->unsignedInteger('views')->default(0)->after('l60');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('aliexpress_metric') && Schema::hasColumn('aliexpress_metric', 'views')) {
            Schema::table('aliexpress_metric', function (Blueprint $table) {
                $table->dropColumn('views');
            });
        }
    }
};
